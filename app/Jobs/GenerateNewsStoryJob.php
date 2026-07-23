<?php
namespace App\Jobs;
use App\Models\Character;
use App\Models\LifeEvent;
use App\Models\Post;
use App\Services\NewsDigestService;
use App\Services\OpenAiTextService;
use App\Services\PromptBuilder;
use App\Services\StoryComposerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
class GenerateNewsStoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 2;
    public function __construct(
        private int $characterId,
        private ?int $forceLifeEventId = null,
        private ?string $instructions = null
    ) {}
    public function handle(PromptBuilder $promptBuilder, OpenAiTextService $openAi, NewsDigestService $newsDigest, StoryComposerService $storyComposer): void
    {
        $character = Character::findOrFail($this->characterId);
        $lifeEvent = $this->forceLifeEventId
            ? LifeEvent::findOrFail($this->forceLifeEventId)
            : LifeEvent::where(function ($q) use ($character) {
                $q->where('character_id', $character->id)->orWhereNull('character_id');
            })->whereNotNull('news_query')->inRandomOrder()->first();
        if (! $lifeEvent) {
            throw new RuntimeException('Nessun life_event con news_query configurato: nulla su cui far commentare la story.');
        }
        if (! $lifeEvent->news_query) {
            throw new RuntimeException("Il life_event scelto ({$lifeEvent->title}) non ha una news_query configurata: non posso generare una story su una notizia.");
        }
        $news = $newsDigest->fetchRecent($lifeEvent->news_query, $lifeEvent->news_category);
        if (empty($news)) {
            throw new RuntimeException("Nessuna notizia recente trovata per '{$lifeEvent->news_query}', salto la story di oggi.");
        }
        $prompt = $promptBuilder->buildStoryCommentPrompt($character, $news, $this->instructions);
        $storyText = $openAi->generateReply($prompt);
        $basePost = Post::where('character_id', $character->id)
            ->where('status', 'published')
            ->whereNotNull('media_urls')
            ->latest('published_at')
            ->first();
        if (! $basePost || empty($basePost->media_urls)) {
            throw new RuntimeException('Nessun post pubblicato da riusare come sfondo per la story.');
        }
        $baseImageBinary = Storage::disk('local')->get($basePost->media_urls[0]);
        $storyImageBinary = $storyComposer->compose($baseImageBinary, $storyText);
        $storyPath = "generations/{$character->tenant_id}/{$character->id}/" . now()->format('Ymd_His') . '_' . Str::random(6) . '_story.png';
        Storage::disk('local')->put($storyPath, $storyImageBinary);
        Post::create([
            'character_id' => $character->id,
            'platform' => 'instagram',
            'media_type' => 'story',
            'status' => 'draft',
            'caption' => null,
            'media_urls' => [$storyPath],
        ]);
    }
}
