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
];
