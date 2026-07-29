<?php

namespace App\Support;

/**
 * Testo del consenso per l'upload di una foto reale (Fase 4 mondo narrativo, documento
 * character-engine-comparse-mondo-narrativo.md sez. 4). PLACEHOLDER, non consulenza legale — da
 * far rivedere da un avvocato prima del lancio pubblico di questa funzione. Solo il meccanismo
 * tecnico (checkbox separata dai Termini di Servizio, evento auditabile con versione esatta
 * accettata) va costruito ora.
 *
 * VERSION va incrementata ogni volta che il testo cambia in modo sostanziale, così i consensi già
 * raccolti restano leggibili rispetto alla versione che l'utente ha davvero accettato in quel
 * momento, anche se il testo corrente cambia in futuro.
 */
class AssetUploadConsentText
{
    public const VERSION = 'v1-bozza-non-revisionata-legalmente';

    public const TEXT = "Dichiaro che questa immagine è generata dall'intelligenza artificiale, oppure è una foto reale di cui possiedo i diritti e il consenso esplicito della persona ritratta all'uso su questa piattaforma (una mia foto, o di una persona che ha acconsentito) — mai la foto di una persona reale riconoscibile senza il suo consenso esplicito. Sono consapevole che l'uso improprio dell'immagine di una persona reale può violare il diritto all'immagine e, se il volto è riconoscibile, la normativa sulla protezione dei dati personali (GDPR). Mi assumo la piena responsabilità di questa dichiarazione; la piattaforma si riserva il diritto di rimuovere il contenuto e sospendere l'account in caso di segnalazione o fondato sospetto di violazione.";
}
