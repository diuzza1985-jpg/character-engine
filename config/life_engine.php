<?php

return [
    // 11.2 — decadimento mood verso baseline ad ogni tick giornaliero.
    'mood_decay_rate' => 0.15,

    // 11.3 — pressione storyline aperte: importanza * min(1, giorni_da_ultimo_aggiornamento / window).
    'storyline_pressure_window_days' => 10,

    // 11.3 — oltre questa soglia una storyline "aperta" senza aggiornamenti viene marcata "abbandonata".
    'storyline_abandon_days' => 35,

    // 11.4 — probabilità base che "oggi succeda qualcosa" (nessun evento da tempo), sale fino a 1
    // man mano che passano i giorni dall'ultimo evento, fino a raggiungere event_stall_window_days.
    'event_base_probability' => 0.2,
    'event_stall_window_days' => 14,

    // 11.6 — finestra di conteggio per i drives derivati dalla category dei life_events.
    'drives_lookback_days' => 60,

    'memory' => [
        // life_events.weight (unsignedInteger, default 50, usato come peso relativo in
        // LifeEventSelector::weightedPick()) normalizzato a una scala 0-1 per memorability.
        'life_event_weight_scale' => 100,

        // Sotto questa soglia in giorni, un evento non può ancora essere "un ricordo"
        'minimo_giorni' => 30,

        // Finestra di massima probabilità di richiamo (plateau)
        'ottimo_inizio_giorni' => 75,
        'ottimo_fine_giorni' => 120,

        // Oltre questa soglia il punteggio scende al floor, ma il ricordo resta
        // disponibile (bassa priorità), non sparisce mai del tutto
        'massimo_giorni' => 365,
        'floor_oltre_massimo' => 0.15,

        // Penalità se lo stesso ricordo è già stato richiamato di recente
        'giorni_minimi_tra_richiami' => 60,
        'penalita_per_richiamo_precedente' => 0.5, // moltiplicatore, si applica per ogni referenced_count

        // Punteggio minimo sotto il quale un candidato non viene nemmeno proposto al cervello editoriale
        'soglia_minima_punteggio' => 0.15,

        // Quanti candidati "ricordo" passare al massimo a EditorialContextBuilder
        'candidati_max' => 3,
    ],
];
