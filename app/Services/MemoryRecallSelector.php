<?php
// app/Services/MemoryRecallSelector.php

namespace App\Services;

use App\Models\Character;
use App\Models\TimelineEntry;
use Illuminate\Support\Collection;

class MemoryRecallSelector
{
    /**
     * Ritorna TUTTI i candidati "ricordo" sopra soglia, ordinati per punteggio decrescente.
     * Nessun limite qui di proposito: il chiamante decide quanti tenerne, stesso pattern già
     * in uso in EditorialContextBuilder::buildRelevantStorylines() (ordina, poi ->take()).
     * Nessuna chiamata esterna — stesso principio di Storyline::pressure().
     */
    public function candidatesFor(Character $character): Collection
    {
        $cfg = config('life_engine.memory');

        return $character->timelineEntries()
            ->get()
            ->map(function (TimelineEntry $entry) use ($cfg) {
                $giorni = (int) $entry->created_at->diffInDays(now());

                return [
                    'entry' => $entry,
                    'giorni_trascorsi' => $giorni,
                    'punteggio' => $this->score($entry, $giorni, $cfg),
                ];
            })
            ->filter(fn (array $c) => $c['punteggio'] >= $cfg['soglia_minima_punteggio'])
            ->sortByDesc('punteggio')
            ->values();
    }

    private function score(TimelineEntry $entry, int $giorniTrascorsi, array $cfg): float
    {
        $curva = $this->campana(
            $giorniTrascorsi,
            $cfg['minimo_giorni'],
            $cfg['ottimo_inizio_giorni'],
            $cfg['ottimo_fine_giorni'],
            $cfg['massimo_giorni'],
            $cfg['floor_oltre_massimo'],
        );

        $penalita = $this->penalitaRichiamoRecente($entry, $cfg);

        return $entry->effectiveMemorability() * $curva * $penalita;
    }

    /**
     * Curva a campana: 0 prima del minimo, sale linearmente fino al plateau,
     * resta a 1 durante il plateau, scende verso il floor dopo il massimo,
     * e non torna mai a 0 (un ricordo vecchio resta possibile, solo improbabile).
     */
    private function campana(
        int $giorni,
        int $minimo,
        int $plateauInizio,
        int $plateauFine,
        int $massimo,
        float $floor,
    ): float {
        if ($giorni < $minimo) {
            return 0.0;
        }

        if ($giorni < $plateauInizio) {
            return ($giorni - $minimo) / max(1, $plateauInizio - $minimo);
        }

        if ($giorni <= $plateauFine) {
            return 1.0;
        }

        if ($giorni <= $massimo) {
            $progresso = ($giorni - $plateauFine) / max(1, $massimo - $plateauFine);
            return 1.0 - ($progresso * (1.0 - $floor));
        }

        return $floor;
    }

    private function penalitaRichiamoRecente(TimelineEntry $entry, array $cfg): float
    {
        if ($entry->last_referenced_at === null) {
            return 1.0;
        }

        $giorniDaUltimoRichiamo = $entry->last_referenced_at->diffInDays(now());

        if ($giorniDaUltimoRichiamo < $cfg['giorni_minimi_tra_richiami']) {
            return 0.0; // troppo presto, escluso del tutto
        }

        // Ogni richiamo precedente riduce ulteriormente la priorità,
        // così un ricordo già usato 3 volte cede il posto a materiale più fresco.
        return pow($cfg['penalita_per_richiamo_precedente'], $entry->referenced_count);
    }
}