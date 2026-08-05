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

    private const GIORNI_SETTIMANA = [
        'Monday' => 'lunedì',
        'Tuesday' => 'martedì',
        'Wednesday' => 'mercoledì',
        'Thursday' => 'giovedì',
        'Friday' => 'venerdì',
        'Saturday' => 'sabato',
        'Sunday' => 'domenica',
    ];

    public static function giornoSettimana(): string
    {
        return self::GIORNI_SETTIMANA[self::now()->format('l')];
    }

    public static function isWeekend(): bool
    {
        return self::now()->isWeekend();
    }

    /**
     * Festività fisse italiane (mese-giorno) + Pasqua/Pasquetta, calcolate con la data odierna
     * (Europe/Rome). Nessuna festività locale/regionale — solo quelle nazionali, generico per
     * qualunque personaggio italiano, non specifico di nessuno.
     */
    private const FESTIVITA_FISSE = [
        '01-01' => 'Capodanno',
        '01-06' => 'Epifania',
        '04-25' => 'Festa della Liberazione',
        '05-01' => 'Festa dei Lavoratori',
        '06-02' => 'Festa della Repubblica',
        '08-15' => 'Ferragosto',
        '11-01' => 'Ognissanti',
        '12-08' => 'Immacolata Concezione',
        '12-24' => 'Vigilia di Natale',
        '12-25' => 'Natale',
        '12-26' => 'Santo Stefano',
        '12-31' => 'Vigilia di Capodanno',
    ];

    public static function festivita(): ?string
    {
        $today = self::now();

        $fissa = self::FESTIVITA_FISSE[$today->format('m-d')] ?? null;
        if ($fissa !== null) {
            return $fissa;
        }

        $pasqua = self::easterDate((int) $today->format('Y'));
        if ($today->isSameDay($pasqua)) {
            return 'Pasqua';
        }
        if ($today->isSameDay($pasqua->copy()->addDay())) {
            return 'Pasquetta';
        }

        return null;
    }

    /**
     * Algoritmo di Gauss/anonimo gregoriano per la data della Pasqua — esatto per qualunque anno
     * del calendario gregoriano, non un'approssimazione. Verificato contro due date reali note:
     * Pasqua 2026 = 5 aprile 2026, Pasqua 2027 = 28 marzo 2027 (vedi TemporalContextTest).
     */
    public static function easterDate(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day, 0, 0, 0, self::TIMEZONE);
    }
}
