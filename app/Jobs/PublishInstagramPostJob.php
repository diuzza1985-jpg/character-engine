<?php
namespace App\Jobs;
use App\Models\Post;
use App\Services\InstagramGraphService;
use App\Services\StoryComposerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
/**
 * ShouldBeUnique (bug reale corretto il 4 settembre 2026): character:publish-due-posts dispatcha
 * questo job a ogni giro (ogni 15 minuti) per qualunque post ancora 'draft' con l'orario arrivato
 * — con dispatch() vero (non più dispatchSync(), 11.30) lo stato non cambia finché un worker non
 * lo processa davvero. Se il worker è lento, occupato o (come successo qui) va giù, ogni giro
 * successivo accodava un ALTRO job per lo stesso post: trovati 5 job duplicati in coda per lo
 * stesso Post#50 dopo che il worker è crashato — se fossero stati processati tutti, avrebbe
 * pubblicato lo stesso post 5 volte su Instagram. uniqueId() = id del post, uniqueFor = più del
 * tempo massimo realistico che il job può impiegare (tries/backoff/attesa container inclusi).
 */
class PublishInstagramPostJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 5;
    public $backoff = [30, 60, 300, 900, 3600];
    public $uniqueFor = 7200;
    public function __construct(private int $postId) {}
    public function uniqueId(): string
    {
        return (string) $this->postId;
    }
    public function handle(InstagramGraphService $instagram): void
    {
        $post = Post::with('character.socialAccount')->findOrFail($this->postId);

        // Idempotenza (stesso incidente del 4 settembre 2026 sopra): se per qualunque motivo
        // questo job gira una seconda volta su un post già pubblicato (coda duplicata, retry
        // manuale, race condition), non ripubblicare mai un contenuto già uscito su Instagram.
        if ($post->status === 'published') {
            Log::info("PublishInstagramPostJob: post {$post->id} già pubblicato, salto (dispatch duplicato).");
            return;
        }

        $account = $post->character->socialAccount;
        if (! $account || $account->status !== 'connected') {
            $post->update(['status' => 'failed']);
            Log::warning("PublishInstagramPostJob: account Instagram non connesso per post {$post->id}");
            return;
        }
        $limit = $instagram->getPublishingLimit($account->ig_user_id, $account->access_token);
        $usage = $limit['data'][0]['quota_usage'] ?? 0;
        $cap = $limit['data'][0]['config']['quota_total'] ?? 100;
        if ($usage >= $cap) {
            Log::warning("PublishInstagramPostJob: quota esaurita ({$usage}/{$cap}) per account {$account->ig_user_id}, rimando post {$post->id} di 1h");
            $this->release(3600);
            return;
        }
        $containerId = match ($post->media_type) {
            'carousel' => $this->buildCarouselContainer($instagram, $post, $account),
            'story' => $this->buildStoryContainer($instagram, $post, $account),
            default => $this->buildSingleImageContainer($instagram, $post, $account),
        };
        $this->waitUntilReady($instagram, $containerId, $account->access_token);
        $published = $instagram->publishContainer($account->ig_user_id, $account->access_token, $containerId);
        $post->update([
            'status' => 'published',
            'platform' => 'instagram',
            'platform_post_id' => $published['id'] ?? null,
            'published_at' => now(),
        ]);
        Log::info("PublishInstagramPostJob: post {$post->id} pubblicato, platform_post_id={$published['id']}");

        $this->shareToStoryIfNeeded($post);
    }

    /**
     * Qualunque post pubblicato che NON sia già una story (image, carousel, con o senza overlay)
     * viene condiviso automaticamente anche in story: stessa immagine (la prima, per un
     * carousel), ricomposta in verticale da StoryComposerService — nessun nuovo testo
     * sovrapposto, è la stessa foto del post, solo nel formato story.
     *
     * Wrappato in try/catch e mai rilanciato: un fallimento qui non deve mai far ritentare
     * l'intero job (che ripubblicherebbe da capo il post originale, già pubblicato con successo
     * sopra) né marcare fallito un post che invece è stato pubblicato correttamente.
     */
    private function shareToStoryIfNeeded(Post $post): void
    {
        if ($post->media_type === 'story' || $post->related_post_id) {
            return; // è già una story, oppure è essa stessa una condivisione: mai incatenare.
        }
        if (Post::where('related_post_id', $post->id)->exists()) {
            return; // già condiviso in precedenza (idempotenza, es. su un eventuale rerun).
        }

        try {
            $sourcePath = $post->media_urls[0] ?? null;
            if (! $sourcePath || ! Storage::disk('local')->exists($sourcePath)) {
                Log::warning("PublishInstagramPostJob: nessuna immagine sorgente per condividere il post {$post->id} in story");
                return;
            }

            // "conversazione" (mockup puro) e "screenshot" (bolle overlay sopra una foto, ma
            // vicine ai bordi laterali) condividono lo stesso bug reale: un crop centrato taglia
            // il testo dei fumetti. Qualunque altro formato è una foto reale senza testo vicino
            // ai bordi, dove il crop-to-fill resta la scelta giusta (default di compose()).
            $cropToFill = ! in_array($post->narrative_format, ['conversazione', 'screenshot'], true);
            $storyBinary = app(StoryComposerService::class)->compose(Storage::disk('local')->get($sourcePath), '', $cropToFill);
            $storyPath = "generations/{$post->character->tenant_id}/{$post->character_id}/"
                .now()->format('Ymd_His').'_'.Str::random(6).'_story_share.png';
            Storage::disk('local')->put($storyPath, $storyBinary);

            $storyPost = Post::create([
                'character_id' => $post->character_id,
                'generation_id' => $post->generation_id,
                'related_post_id' => $post->id,
                'platform' => 'instagram',
                'media_type' => 'story',
                'narrative_format' => 'story_share',
                'status' => 'draft',
                'caption' => '',
                'media_urls' => [$storyPath],
            ]);

            // dispatchSync, non dispatch(): bug reale trovato verificando la coda in vista del
            // deploy — nessun queue worker gira da nessuna parte (né in locale né altrove),
            // quindi questo job restava bloccato per sempre in jobs (verificato: 4 job mai
            // processati, 3 story rimaste in bozza dal 31 luglio). Stesso principio già seguito
            // ovunque nel resto del codebase (GenerateInstagramPostJob, ProcessScheduledPostJob,
            // ...): mai un dispatch asincrono senza un worker reale a processarlo.
            self::dispatchSync($storyPost->id);
            Log::info("PublishInstagramPostJob: post {$post->id} condiviso anche in story (post {$storyPost->id})");
        } catch (Throwable $e) {
            Log::warning("PublishInstagramPostJob: condivisione in story fallita per post {$post->id}: {$e->getMessage()}");
        }
    }
    private function buildSingleImageContainer(InstagramGraphService $instagram, Post $post, $account): string
    {
        $mediaUrl = URL::temporarySignedRoute('media.show', now()->addDay(), ['post' => $post->id]);
        Log::info("PublishInstagramPostJob: creo container singolo per post {$post->id}", ['media_url' => $mediaUrl]);
        $container = $instagram->createMediaContainer($account->ig_user_id, $account->access_token, $mediaUrl, $post->caption ?? '');
        if (empty($container['id'])) {
            throw new RuntimeException('Creazione media container fallita: ' . json_encode($container));
        }
        return $container['id'];
    }
    private function buildCarouselContainer(InstagramGraphService $instagram, Post $post, $account): string
    {
        $childrenIds = [];
        foreach ($post->media_urls as $index => $path) {
            $mediaUrl = URL::temporarySignedRoute('media.show', now()->addDay(), ['post' => $post->id, 'index' => $index]);
            Log::info("PublishInstagramPostJob: creo carousel child {$index} per post {$post->id}", ['media_url' => $mediaUrl]);
            $child = $instagram->createCarouselChildContainer($account->ig_user_id, $account->access_token, $mediaUrl);
            if (empty($child['id'])) {
                throw new RuntimeException("Creazione carousel child container fallita (slide {$index}): " . json_encode($child));
            }
            $this->waitUntilReady($instagram, $child['id'], $account->access_token);
            $childrenIds[] = $child['id'];
        }
        if (count($childrenIds) < 2) {
            throw new RuntimeException('Un carosello richiede almeno 2 slide pronte, trovate: ' . count($childrenIds));
        }
        $parent = $instagram->createCarouselContainer($account->ig_user_id, $account->access_token, $childrenIds, $post->caption ?? '');
        if (empty($parent['id'])) {
            throw new RuntimeException('Creazione carousel container padre fallita: ' . json_encode($parent));
        }
        return $parent['id'];
    }
    private function buildStoryContainer(InstagramGraphService $instagram, Post $post, $account): string
    {
        $mediaUrl = URL::temporarySignedRoute('media.show', now()->addDay(), ['post' => $post->id]);
        Log::info("PublishInstagramPostJob: creo story container per post {$post->id}", ['media_url' => $mediaUrl]);
        $container = $instagram->createStoryContainer($account->ig_user_id, $account->access_token, $mediaUrl);
        if (empty($container['id'])) {
            throw new RuntimeException('Creazione story container fallita: ' . json_encode($container));
        }
        return $container['id'];
    }
    /**
     * Bug reale corretto il 3 settembre 2026 (Post#50): i 6 tentativi con sleep(5) precedenti (30s
     * totali) non bastavano — Meta a volte impiega più di mezzo minuto a rendere davvero
     * disponibile un container che poco prima segnalava già FINISHED (errore 9007/2207027 "Media
     * ID is not available" visto in produzione nonostante questo stesso polling). 1 tentativo al
     * minuto per non più di 5 minuti, coerente con i tempi di elaborazione osservati da Meta.
     * Sleep::for(), non sleep(): fakeable nei test (Sleep::fake()), niente attese reali in CI.
     */
    private function waitUntilReady(InstagramGraphService $instagram, string $containerId, string $accessToken): void
    {
        $maxAttempts = 5;
        $intervalSeconds = 60;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $status = $instagram->getContainerStatus($containerId, $accessToken);
            if ($status === 'FINISHED') {
                return;
            }
            if (in_array($status, ['ERROR', 'EXPIRED'], true)) {
                throw new RuntimeException("Container {$containerId} in stato {$status}");
            }
            if ($i < $maxAttempts - 1) {
                Sleep::for($intervalSeconds)->seconds();
            }
        }
        throw new RuntimeException("Container {$containerId} non pronto dopo {$maxAttempts} tentativi (stato ancora IN_PROGRESS)");
    }
    public function failed(Throwable $exception): void
    {
        Post::where('id', $this->postId)->update(['status' => 'failed']);
        Log::error("PublishInstagramPostJob fallito per post {$this->postId}: " . $exception->getMessage());
    }
}
