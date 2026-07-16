<?php
namespace App\Console\Commands;
use App\Models\Character;
use App\Services\EditorialCycleService;
use Illuminate\Console\Command;

class RunEditorialCycle extends Command
{
    protected $signature = 'character:run-editorial-cycle {character : Slug o ID del personaggio}';
    protected $description = 'Esegue il ciclo editoriale (11.7): una chiamata GPT decide se e cosa pubblicare oggi, a partire dallo stato prodotto dal motore di vita';

    public function handle(EditorialCycleService $cycle): int
    {
        $arg = $this->argument('character');
        $character = ctype_digit((string) $arg)
            ? Character::find((int) $arg)
            : Character::where('slug', $arg)->first();

        if (! $character) {
            $this->error("Personaggio \"{$arg}\" non trovato.");
            return self::FAILURE;
        }

        $this->info("Ciclo editoriale per {$character->name} (#{$character->id})...");

        $result = $cycle->run($character);

        if (! $result['decision']) {
            $this->error("Ciclo fallito. generation #{$result['generation']->id} marcata failed — dettagli: " . ($result['generation']->output['error'] ?? 'sconosciuto'));
            return self::FAILURE;
        }

        $this->line('');
        $this->line('=== DECISIONE DEL CERVELLO EDITORIALE ===');
        $this->line(json_encode($result['decision'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->line('');

        $this->info("generation #{$result['generation']->id} (status: {$result['generation']->status})");
        $this->info("editorial_decision #{$result['editorial_decision']->id} (decisione: {$result['editorial_decision']->decisione})");

        if ($result['post']) {
            $this->info("post #{$result['post']->id} creato in draft (formato: {$result['post']->media_type}).");
        } else {
            $this->comment('Nessun post creato (decisione: non_pubblicare).');
        }

        return self::SUCCESS;
    }
}
