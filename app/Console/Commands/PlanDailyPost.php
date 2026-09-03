<?php

namespace App\Console\Commands;

use App\Jobs\GenerateInstagramPostJob;
use App\Models\Character;
use App\Models\Generation;
use App\Models\Post;
use App\Support\TemporalContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Automazione giornaliera (richiesta dall'utente, 31 luglio 2026): ogni personaggio attivo
 * decide DA SOLO, una volta al giorno, se pubblicare oggi e — se sì — anche a che ora
 * (EditorialBrainService::orario_pubblicazione, validato lì), non solo cosa. Indipendente da
 * WeeklyScheduleSlot (che resta disponibile per chi vuole un orario fisso deciso a mano):
 * questo comando non richiede nessuno slot configurato, gira per QUALUNQUE personaggio attivo.
 *
 * Genera già il contenuto completo (stesso GenerateInstagramPostJob del ciclo editoriale
 * esistente, non un secondo call separato "solo per l'orario") — il post resta in stato draft
 * con scheduled_at valorizzato; character:publish-due-posts lo pubblicherà da solo al momento
 * giusto, senza rigenerare nulla.
 *
 * Schedulato ogni 15 minuti (non più una singola dailyAt('06:00')), con un controllo di
 * idempotenza qui sotto ("ha già pianificato oggi?") — bug reale osservato: su una macchina di
 * sviluppo che si sospende (WSL2/Windows, non un server sempre acceso), un trigger a un singolo
 * minuto esatto viene perso per sempre se l'ambiente è sospeso esattamente in quel momento,
 * saltando l'intera giornata senza nessun recupero. Ricontrollare ogni 15 minuti "ho già
 * pianificato oggi per questo personaggio?" rende l'automazione resiliente: appena l'ambiente si
 * risveglia, il giro successivo se ne accorge e pianifica comunque, invece di aspettare il giorno dopo.
 */
class PlanDailyPost extends Command
{
    protected $signature = 'character:plan-daily-post {character? : Slug o ID del personaggio, opzionale — se omesso gira su tutti i personaggi attivi}';

    protected $description = 'Il cervello editoriale decide da solo se e cosa pubblicare oggi, incluso l\'orario — genera il post in bozza con scheduled_at valorizzato';

    public function handle(): int
    {
        $characters = $this->resolveCharacters();

        if ($characters->isEmpty()) {
            $this->error('Nessun personaggio trovato.');

            return self::FAILURE;
        }

        foreach ($characters as $character) {
            if ($this->alreadyPlannedToday($character)) {
                $this->comment("{$character->name}: già pianificato oggi, salto.");

                continue;
            }

            $this->info("Pianificazione giornaliera per {$character->name} (#{$character->id})...");

            try {
                // Bus::dispatchNow, non ::dispatchSync: GenerateInstagramPostJob implementa
                // ShouldQueue, dispatchSync instrada su SyncQueue::push() che non propaga il
                // valore di ritorno di handle() (stesso principio già in ProcessScheduledPostJob).
                $post = Bus::dispatchNow(new GenerateInstagramPostJob($character->id, useEditorialBrain: true));

                if (! $post) {
                    $this->comment('Decisione: non pubblicare oggi.');

                    continue;
                }

                $this->info("Post #{$post->id} generato in bozza, programmato per " . ($post->scheduled_at?->format('d/m/Y H:i') ?? '(nessun orario)') . '.');
            } catch (Throwable $e) {
                $this->error("Fallito per {$character->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * "Oggi" in ora italiana (TemporalContext), non nell'UTC del server — stesso principio già
     * applicato altrove in questa automazione (bug reale già trovato e corretto per
     * season()/timeOfDay()). EditorialCycleService::run() crea sempre una Generation
     * purpose=editorial_decision, anche quando la decisione è "non_pubblicare" — basta quella per
     * sapere se il personaggio ha già avuto il suo giro decisionale oggi, non serve controllare
     * se abbia prodotto un Post.
     *
     * Secondo controllo aggiunto il 3 settembre 2026: un post già pronto (bozza o pubblicato) con
     * scheduled_at di oggi conta come "già pianificato" anche SENZA una Generation odierna — copre
     * il caso di un post riprogrammato a mano da un giorno precedente (es. un post fallito per un
     * errore transitorio e non ripubblicato in tempo, rimesso in bozza con scheduled_at spostato a
     * oggi invece di rigenerarlo da capo interrogando di nuovo le API) senza dover insegnare a
     * questo comando nulla su un personaggio specifico: qualunque post già pronto per oggi basta.
     */
    private function alreadyPlannedToday(Character $character): bool
    {
        $startOfDay = TemporalContext::now()->startOfDay()->utc();
        $endOfDay = TemporalContext::now()->endOfDay()->utc();

        $hasDecisionToday = Generation::where('character_id', $character->id)
            ->where('purpose', 'editorial_decision')
            ->where('created_at', '>=', $startOfDay)
            ->exists();

        if ($hasDecisionToday) {
            return true;
        }

        return Post::where('character_id', $character->id)
            ->whereIn('status', ['draft', 'published'])
            ->whereBetween('scheduled_at', [$startOfDay, $endOfDay])
            ->exists();
    }

    private function resolveCharacters()
    {
        $arg = $this->argument('character');

        if ($arg) {
            $character = ctype_digit((string) $arg)
                ? Character::find((int) $arg)
                : Character::where('slug', $arg)->first();

            return $character ? collect([$character]) : collect();
        }

        return Character::where('status', 'active')->get();
    }
}
