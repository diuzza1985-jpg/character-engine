<?php
// app/Console/Commands/TestMemoryRecall.php

namespace App\Console\Commands;

use App\Models\Character;
use App\Services\MemoryRecallSelector;
use Illuminate\Console\Command;

class TestMemoryRecall extends Command
{
    protected $signature = 'character:test-memory-recall {character}';
    protected $description = 'Stampa i candidati ricordo per un personaggio, senza chiamate esterne';

    public function handle(MemoryRecallSelector $selector): int
    {
        $character = Character::where('slug', $this->argument('character'))->firstOrFail();

        $candidates = $selector->candidatesFor($character);

        if ($candidates->isEmpty()) {
            $this->info('Nessun candidato sopra soglia.');
            return self::SUCCESS;
        }

        foreach ($candidates as $c) {
            $this->line(sprintf(
                '[%.2f] #%d "%s" — %d giorni fa (richiamato %d volte, ultima %s)',
                $c['punteggio'],
                $c['entry']->id,
                $c['entry']->title,
                $c['giorni_trascorsi'],
                $c['entry']->referenced_count,
                $c['entry']->last_referenced_at?->diffForHumans() ?? 'mai',
            ));
        }

        return self::SUCCESS;
    }
}