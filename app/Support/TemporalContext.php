<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class TemporalContext
{
    /**
     * L'app gira in UTC (config('app.timezone')) ma i personaggi sono persone italiane reali:
     * "che ore sono"/"che stagione è" devono riflettere l'ora locale italiana, non quella del
     * server. Bug reale trovato verificando come integrare l'orario di pubblicazione nel
     * cervello editoriale (season()/timeOfDay() leggevano now() grezzo, cioè UTC): un momento
     * come le 23:00 italiane (estate, UTC+2) veniva letto come le 21:00 UTC, ancora "sera" per
     * pochi minuti invece di "notte" — sistematicamente sfasato di 1-2 ore per tutta la vita di
     * questa funzionalità, non solo da oggi.
     */
    public const TIMEZONE = 'Europe/Rome';

    public static function now(): Carbon
    {
        return now(self::TIMEZONE);
    }

    /**
     * Converte un "HH:MM" (inteso come ora italiana di oggi, es. una scelta del cervello
     * editoriale) in un istante UTC corretto per lo storage — mai assegnare direttamente un
     * Carbon con timezone Europe/Rome a una colonna datetime: Eloquent non lo converte da solo
     * (fromDateTime() chiama solo format(), che stampa le cifre così come sono nel timezone
     * corrente dell'oggetto), quindi "20:35 Europe/Rome" verrebbe salvato e riletto come se fosse
     * "20:35 UTC" — 1-2 ore di sfasamento reale, trovato verificando con una generazione vera.
     */
    public static function todayAt(string $hhMm): Carbon
    {
        return self::now()->setTimeFromTimeString($hhMm)->utc();
    }

    public static function season(): string
    {
        $month = (int) self::now()->format('n');

        return match (true) {
            in_array($month, [12, 1, 2], true) => 'inverno',
            in_array($month, [3, 4, 5], true) => 'primavera',
            in_array($month, [6, 7, 8], true) => 'estate',
            default => 'autunno',
        };
    }

    public static function timeOfDay(): string
    {
        $hour = (int) self::now()->format('G');

        return match (true) {
            $hour < 6 => 'notte',
            $hour < 12 => 'mattina',
            $hour < 17 => 'pomeriggio',
            $hour < 21 => 'sera',
            default => 'notte',
        };
    }
}
