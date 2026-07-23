<?php
namespace App\Console\Commands;
use App\Models\Character;
use App\Models\CommentReply;
use App\Models\Post;
use App\Services\InstagramGraphService;
use App\Services\OpenAiTextService;
use App\Services\PromptBuilder;
use Illuminate\Console\Command;
use Throwable;
class ReplyToInstagramComments extends Command
{
    protected $signature = 'instagram:reply-comments {character=sofia} {--posts=15} {--max-batch=25}';
    protected $description = 'Legge i commenti sui media pubblicati e risponde a tutti quelli nuovi con UNA sola chiamata OpenAI per run';
    public function handle(InstagramGraphService $instagram, PromptBuilder $promptBuilder, OpenAiTextService $openAi): int
    {
        $character = Character::where('slug', $this->argument('character'))->first();
        if (! $character) {
            $this->error('Personaggio non trovato.');
            return self::FAILURE;
        }
        $account = $character->socialAccount;
        if (! $account || $account->status !== 'connected') {
            $this->error('Account Instagram non connesso per questo personaggio.');
            return self::FAILURE;
        }
        try {
            $mediaItems = $instagram->getUserMedia($account->ig_user_id, $account->access_token, (int) $this->option('posts'));
        } catch (Throwable $e) {
            $this->error('Lettura media da Instagram fallita: ' . $e->getMessage());
            return self::FAILURE;
        }
        if (empty($mediaItems)) {
            $this->info('Nessun media pubblicato trovato su Instagram.');
            return self::SUCCESS;
        }
        // FASE 1: raccogli tutti i commenti nuovi, zero chiamate OpenAI qui.
        $pending = [];
        $mediaByCommentId = [];
        $skipped = 0;
        foreach ($mediaItems as $media) {
            $mediaId = $media['id'] ?? null;
            if (! $mediaId) {
                continue;
            }
            try {
                $comments = $instagram->getMediaComments($mediaId, $account->access_token);
            } catch (Throwable $e) {
                $this->warn("Media {$mediaId}: lettura commenti fallita — " . $e->getMessage());
                continue;
            }
            foreach ($comments as $comment) {
                $igCommentId = $comment['id'] ?? null;
                $text = trim($comment['text'] ?? '');
                if (! $igCommentId || $text === '') {
                    continue;
                }
                if (CommentReply::where('ig_comment_id', $igCommentId)->exists()) {
                    continue;
                }
                if (preg_match('/https?:\/\//i', $text)) {
                    CommentReply::create([
                        'character_id' => $character->id, 'post_id' => null,
                        'ig_media_id' => $mediaId, 'ig_comment_id' => $igCommentId,
                        'commenter_username' => $comment['username'] ?? null, 'comment_text' => $text,
                        'status' => 'skipped',
                    ]);
                    $skipped++;
                    continue;
                }
                $pending[] = ['id' => (string) $igCommentId, 'username' => $comment['username'] ?? null, 'text' => $text];
                $mediaByCommentId[$igCommentId] = $mediaId;
            }
        }
        if (empty($pending)) {
            $this->info("Nessun commento nuovo. Nessuna chiamata OpenAI effettuata. Commenti con link saltati: {$skipped}.");
            return self::SUCCESS;
        }
        $maxBatch = (int) $this->option('max-batch');
        if (count($pending) > $maxBatch) {
            $this->warn('Trovati ' . count($pending) . " commenti nuovi, ne elaboro solo {$maxBatch} in questo giro (limite --max-batch); il resto verrà ripreso al prossimo run.");
            $pending = array_slice($pending, 0, $maxBatch);
        }
        $localPosts = Post::where('character_id', $character->id)
            ->whereIn('platform_post_id', array_unique(array_values($mediaByCommentId)))
            ->pluck('id', 'platform_post_id');
        // FASE 2: UNA sola chiamata OpenAI per tutti i commenti raccolti.
        $prompt = $promptBuilder->buildBatchCommentReplyPrompt($character, $pending);
        try {
            $repliesRaw = $openAi->generateBatchReplies($prompt);
        } catch (Throwable $e) {
            $this->error('Generazione risposte in batch fallita: ' . $e->getMessage() . ' — verrà ritentato al prossimo run.');
            return self::FAILURE;
        }
        $replyByCommentId = [];
        foreach ($repliesRaw as $item) {
            if (isset($item['comment_id'])) {
                $replyByCommentId[(string) $item['comment_id']] = trim((string) ($item['reply'] ?? ''));
            }
        }
        // FASE 3: pubblica ogni risposta su Instagram (una chiamata a Instagram per commento, gratuita).
        $newReplies = 0;
        foreach ($pending as $comment) {
            $igCommentId = $comment['id'];
            $mediaId = $mediaByCommentId[$igCommentId];
            $replyText = $replyByCommentId[$igCommentId] ?? null;
            if (! $replyText) {
                $this->warn("L'AI non ha restituito una risposta per il commento {$igCommentId}, verrà ritentato al prossimo giro.");
                continue;
            }
            try {
                $replied = $instagram->replyToComment($igCommentId, $account->access_token, $replyText);
                CommentReply::create([
                    'character_id' => $character->id, 'post_id' => $localPosts[$mediaId] ?? null,
                    'ig_media_id' => $mediaId, 'ig_comment_id' => $igCommentId,
                    'commenter_username' => $comment['username'], 'comment_text' => $comment['text'],
                    'reply_text' => $replyText, 'ig_reply_id' => $replied['id'] ?? null,
                    'status' => 'replied',
                ]);
                $this->info("Media {$mediaId} — @{$comment['username']}: \"{$comment['text']}\" -> \"{$replyText}\"");
                $newReplies++;
            } catch (Throwable $e) {
                CommentReply::create([
                    'character_id' => $character->id, 'post_id' => $localPosts[$mediaId] ?? null,
                    'ig_media_id' => $mediaId, 'ig_comment_id' => $igCommentId,
                    'commenter_username' => $comment['username'], 'comment_text' => $comment['text'],
                    'reply_text' => $replyText, 'status' => 'failed',
                ]);
                $this->warn("Pubblicazione risposta al commento {$igCommentId} fallita: " . $e->getMessage());
            }
        }
        $this->newLine();
        $this->info("Chiamate OpenAI in questo run: 1. Nuove risposte pubblicate: {$newReplies}. Commenti con link saltati: {$skipped}.");
        return self::SUCCESS;
    }
}
