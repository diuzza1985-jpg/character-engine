<?php

namespace App\Console\Commands;

use App\Jobs\PublishInstagramPostJob;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Automazione giornaliera (richiesta dall'utente, 31 luglio 2026): il cervello editoriale
 * (character:plan-daily-post) genera già il post completo con scheduled_at valorizzato — questo
 * comando gira frequentemente (schedule:process-due esistente docet, stessa cadenza) e pubblica
 * SOLO quando arriva quel momento, senza rigenerare nulla. PublishInstagramPostJob aggiorna già
 * lo status a 'published'/'failed' da solo: nessun rischio di ripubblicare lo stesso post al
 * prossimo giro (esce naturalmente dalla query 'draft').
 */
class PublishDuePosts extends Command
{
    protected $signature = 'character:publish-due-posts';

    protected $description = 'Pubblica su Instagram i post già generati (draft) il cui orario programmato (scheduled_at) è arrivato';

    /**
     * Bug reale osservato su questa macchina di sviluppo (WSL2/Windows sospeso per oltre 2
     * giorni): al risveglio, un post rimasto in coda ore o giorni fa rischierebbe di essere
     * pubblicato comunque, con un contenuto scritto per un momento della giornata che non
     * corrisponde più a quello reale (es. "buonasera" pubblicato la mattina dopo) — il principio
     * cardine di questa automazione ("il contenuto dipende dall'ora") verrebbe tradito proprio
     * nel caso in cui serve di più. Meglio marcare 'failed' con un motivo chiaro (loggato) che
     * pubblicare un contenuto ormai fuori tempo.
     */
    private const MAX_STALENESS_HOURS = 3;

    public function handle(): int
    {
        $duePosts = Post::where('status', 'draft')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($duePosts->isEmpty()) {
            $this->info('Nessun post in programma per ora.');

            return self::SUCCESS;
        }

        foreach ($duePosts as $post) {
            $staleness = $post->scheduled_at->diffInHours(now());

            if ($staleness > self::MAX_STALENESS_HOURS) {
                $post->update(['status' => 'failed']);
                $message = "Post #{$post->id} scartato: programmato per {$post->scheduled_at->format('d/m/Y H:i')}, ormai {$staleness}h fa (soglia " . self::MAX_STALENESS_HOURS . 'h) — probabile ambiente sospeso, contenuto non più coerente con l\'orario reale.';
                $this->warn($message);
                Log::warning($message);

                continue;
            }

            $this->info("Pubblico post #{$post->id} (character_id={$post->character_id}, programmato per {$post->scheduled_at->format('d/m/Y H:i')})...");

            try {
                PublishInstagramPostJob::dispatchSync($post->id);
                $post->refresh();
                $this->info("Stato finale: {$post->status}");
            } catch (Throwable $e) {
                $this->error("Fallito: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
