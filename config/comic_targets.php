<?php

/*
 * Bersagli comici per personaggio (slug), usati da character:check-comic-targets. L'elenco di
 * Sofia è lo stesso della vecchia routine cloud (24 luglio 2026), preso dalla sua bible: il
 * classificatore deve scegliere SOLO tra questi, o "nessuno" se il post non è una battuta.
 */
return [
    // Finestra e soglia della segnalazione: lo stesso bersaglio 2+ volte in 15 giorni.
    'window_days' => 15,
    'repeat_threshold' => 2,

    'characters' => [
        'sofia' => [
            'bug che spariscono appena arriva un collega',
            'aggiornamenti che rompono qualcosa che funzionava',
            'password dimenticate',
            'riunioni che potevano essere una mail',
            'browser con troppe schede aperte',
            'computer che si aggiorna nel momento peggiore',
            "l'AI che cambia modello ogni settimana / scrive troppo o sbaglia",
            'richieste improbabili dei clienti',
            'multitasking lavoro/famiglia/casa',
            'burocrazia italiana',
            'ospedali/prenotazioni (CUP) impossibili',
            'correttore automatico',
            'il caffè',
            'occhiali che spariscono',
            'Fernando e i suoi progetti improvvisati',
            'Pippo che interrompe le videocall',
            'Minnie che cammina sulla tastiera',
            'Sushi che osserva in silenzio',
        ],
    ],
];
