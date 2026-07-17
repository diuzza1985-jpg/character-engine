<?php
namespace App\Jobs;
use App\Models\Post;
use App\Models\ScheduledPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Throwable;
class ProcessScheduledPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 1;
    public function __construct(private int $scheduledPostId) {}
    public function handle(): void
    {
        $scheduledPost = ScheduledPost::findOrFail($this->scheduledPostId);
        if ($scheduledPost->status !== 'pending') {
            return;
        }
        $scheduledPost->update(['status' => 'processing']);
        try {
            $postType = $scheduledPost->post_type === 'random'
                ? collect(['image', 'carousel', 'story'])->random()
                : $scheduledPost->post_type;
            $characterId = $scheduledPost->character_id;
            $lifeEventId = $scheduledPost->mode === 'manual' ? $scheduledPost->life_event_id : null;
            $instructions = $scheduledPost->instructions;
            if ($postType === 'story') {
                // Invariato: le story da notizia restano fuori dalla riconciliazione (11.8, fuori scope).
                GenerateNewsStoryJob::dispatchSync($characterId, $lifeEventId, $instructions);
                $draftPost = Post::where('character_id', $characterId)
                    ->where('status', 'draft')
                    ->latest('id')
                    ->first();
                if (! $draftPost) {
                    throw new RuntimeException('Generazione completata ma nessun post draft trovato da pubblicare.');
                }
            } elseif ($scheduledPost->mode === 'manual') {
                // Momento scelto a mano per questo slot: pipeline fissa invariata (life event forzato, 12.1).
                // Bus::dispatchNow (non ::dispatchSync) perché GenerateInstagramPostJob implementa
                // ShouldQueue: dispatchSync instrada su SyncQueue::push(), che esegue il job ma NON
                // propaga il valore di ritorno di handle() — dispatchNow lo restituisce davvero.
                $draftPost = Bus::dispatchNow(new GenerateInstagramPostJob($characterId, $postType, $lifeEventId, $instructions));
                if (! $draftPost) {
                    throw new RuntimeException('Generazione completata ma nessun post draft trovato da pubblicare.');
                }
            } else {
                // Slot automatico: il cervello editoriale decide se e cosa pubblicare (11.7/11.12),
                // non più una selezione fissa da life_events (12.1). $postType non usato: il
                // formato lo decide il cervello editoriale stesso. $instructions passate come
                // guida al contenuto (11.14) — non forzano la pubblicazione, solo
                // max_giorni_silenzio può farlo.
                $draftPost = Bus::dispatchNow(new GenerateInstagramPostJob($characterId, instructions: $instructions, useEditorialBrain: true));
                if (! $draftPost) {
                    // non_pubblicare: esito legittimo, non un errore — non c'è nulla da pubblicare oggi.
                    $scheduledPost->update(['status' => 'skipped']);
                    return;
                }
            }
            $scheduledPost->update(['status' => 'generated', 'post_id' => $draftPost->id]);
            PublishInstagramPostJob::dispatchSync($draftPost->id);
            $scheduledPost->update(['status' => 'published']);
        } catch (Throwable $e) {
            $scheduledPost->update(['status' => 'failed', 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
