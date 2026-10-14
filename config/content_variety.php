<?php

/**
 * Pool generico di piccoli spunti casuali offerti ai prompt di generazione dei post (11.7 e
 * pipeline legacy) per rompere la convergenza osservata: a parità di tema/rubrica (es.
 * biblioteca, passeggiata, serata giochi) sia il testo che l'immagine tendevano sempre alla
 * stessa formulazione, perché nulla nel prompt cambiava realmente da un'occorrenza all'altra
 * dello stesso tema — solo la lista "non ripeterti" basata sui post recenti, utile contro la
 * ripetizione letterale ma inutile a variare la sostanza quando il tema si ripresenta a distanza
 * di settimane.
 *
 * Due gruppi separati (non un'unica lista) perché testo e prompt_immagine vengono variati con
 * pescate indipendenti da PromptVarietySeedGenerator: altrimenti lo stesso seme casuale
 * finirebbe per condizionare entrambi insieme, riducendo la combinatoria reale invece di
 * aumentarla.
 *
 * Nessuna riga qui è legata a un personaggio o a un tema specifico (vale per qualunque bible):
 * il contenuto specifico di ogni personaggio resta nella bible, non qui.
 */
return [
    'testo' => [
        'dettaglio_sensoriale' => [
            'un suono di fondo particolare (non silenzio assoluto: qualcosa che si sente ma non si vede)',
            'un odore preciso legato al momento (non generico: che odore ha esattamente quell\'istante)',
            'un cambiamento di luce improvviso (una nuvola che passa, un riflesso, una lampada che si accende)',
            'una consistenza o una temperatura inaspettata al tatto',
            'un rumore che interrompe per un attimo, poi tutto torna come prima',
            'un colore che spicca più degli altri nella scena, quasi per caso',
            'un sapore che resta in bocca più del previsto',
        ],
        'piccolo_imprevisto' => [
            'un piccolo contrattempo pratico da risolvere al volo (un dettaglio realistico, non un drama)',
            'una telefonata o un messaggio che interrompe per un attimo il momento',
            'qualcosa che va storto in modo minimo e quasi comico',
            'un ritardo di pochi minuti rispetto a quanto previsto',
            'un oggetto che manca all\'appello e va cercato',
            'un incontro casuale non programmato (anche solo un saluto veloce)',
            'un cambio di programma dell\'ultimo minuto, piccolo, non drammatico',
            'una piacevole sorpresa non pianificata',
            'il meteo che smentisce le aspettative di qualche grado o di una nuvola in più',
        ],
        'angolo_narrativo' => [
            'parti da un dettaglio concreto e piccolo, non dal quadro generale',
            'parti da un pensiero improvviso, quasi una divagazione',
            'parti da un paragone o un\'associazione inattesa',
            'parti da una domanda che ti sei fatto/a davvero in quel momento',
            'parti da un ricordo tornato a galla senza motivo apparente',
            'parti dal punto di vista di un dettaglio secondario della scena, non dal soggetto principale',
            'parti da una battuta o un\'osservazione ironica su te stesso/a',
        ],
        'venatura_emotiva' => [
            'un pizzico di nostalgia, appena accennato',
            'un\'impazienza leggera, subito ammorbidita dall\'autoironia',
            'una curiosità inattesa per qualcosa di marginale',
            'un orgoglio piccolo, quasi imbarazzato',
            'una tenerezza improvvisa per un dettaglio banale',
            'una distrazione di fondo, come se la testa fosse un po\' altrove',
            'una soddisfazione minima ma genuina',
        ],
        'vincolo_di_scrittura' => [
            'scrivilo come se lo stessi raccontando di getto a un amico, senza rilettura',
            'evita la prima immagine o parola che ti verrebbe più naturale per descrivere la scena: cercane una meno scontata',
            'usa una frase più corta del solito per chiudere il testo',
            'lascia una frase in sospeso, come un pensiero non del tutto concluso',
            'usa una similitudine o un paragone che non useresti di solito',
            'racconta il momento come se lo stessi rivivendo, non come se lo stessi descrivendo dall\'esterno',
        ],
    ],

    // Usati solo dove prompt_immagine è testo libero (EditorialBrainService): la pipeline legacy
    // vincola la scena a un vocabolario fisso (SceneElement), dove uno spunto visivo libero
    // rischierebbe di spingere il modello a inventare un valore fuori da quella lista.
    'immagine' => [
        'inquadratura' => [
            'primo piano su un dettaglio, non sul viso',
            'piano medio, soggetto leggermente defilato dal centro',
            'campo più largo del solito, con più spazio vuoto intorno al soggetto',
            'inquadratura dall\'alto',
            'di profilo o di tre quarti, non frontale',
            'ripreso di spalle o mentre si allontana',
            'attraverso un elemento in primo piano (una finestra, una porta, un vetro)',
        ],
        'luce' => [
            'controluce, sagoma leggermente in ombra',
            'luce calda e bassa, tipica del tardo pomeriggio',
            'luce fredda e diffusa, giornata nuvolosa',
            'una fonte di luce artificiale visibile nella scena (lampada, insegna, schermo)',
            'ombre lunghe e marcate',
            'luce che filtra da una sola direzione, il resto della scena più in ombra',
        ],
        'dettaglio_di_sfondo' => [
            'un elemento incidentale sullo sfondo, non il soggetto della foto',
            'una texture o una superficie particolare in primo piano rispetto al soggetto',
            'uno scorcio parzialmente fuori fuoco dietro al soggetto',
            'un oggetto lasciato a metà o fuori posto, come nella vita reale',
            'una porzione della scena tagliata dall\'inquadratura, come in una foto presa al volo',
        ],
        'composizione' => [
            'spazio negativo marcato su un lato dell\'inquadratura',
            'inquadratura leggermente dinamica, non perfettamente allineata',
            'il soggetto non al centro ma spostato verso un bordo',
            'un riflesso (vetro, specchio, superficie bagnata) che raddoppia parte della scena',
            'una linea diagonale forte che attraversa l\'inquadratura',
        ],
        'atmosfera_meteo' => [
            'un piccolo segno del meteo del momento (vento, pioggia leggera, aria secca, umidità) coerente con la stagione',
            'una condizione di luce naturale legata a un momento specifico della giornata, non generica',
            'un dettaglio di temperatura percepibile visivamente (fiato che si condensa, una giacca aperta o chiusa)',
        ],
    ],
];
