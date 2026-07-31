<?php

namespace App\Console\Commands;

use App\Jobs\GenerateInstagramPostJob;
use App\Models\Character;
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
