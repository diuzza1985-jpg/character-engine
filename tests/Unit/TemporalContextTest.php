<?php

namespace Tests\Unit;

use App\Support\TemporalContext;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bug reale trovato costruendo l'automazione giornaliera: season()/timeOfDay() leggevano now()
 * grezzo (UTC, il timezone dell'app) invece dell'ora italiana reale — un'istante UTC vicino alla
 * mezzanotte italiana veniva classificato con il giorno/ora sbagliati. Questi test fissano
 * l'istante UTC con Carbon::setTestNow() e verificano il risultato atteso in Europe/Rome, non in UTC.
 */
class TemporalContextTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_time_of_day_uses_italian_time_not_utc(): void
    {
        // 22:30 UTC in estate (CEST, UTC+2) = 00:30 in Italia -> "notte", non "sera" come direbbe l'UTC grezzo.
        Carbon::setTestNow(Carbon::create(2026, 7, 15, 22, 30, 0, 'UTC'));

        $this->assertSame('notte', TemporalContext::timeOfDay());
    }

    public function test_time_of_day_morning_boundary_in_italian_time(): void
    {
        // 04:30 UTC in estate = 06:30 in Italia -> "mattina", non "notte" come direbbe l'UTC grezzo.
        Carbon::setTestNow(Carbon::create(2026, 7, 15, 4, 30, 0, 'UTC'));

        $this->assertSame('mattina', TemporalContext::timeOfDay());
    }

    public function test_season_uses_italian_date_at_year_boundary(): void
    {
        // 23:30 UTC del 28 febbraio in inverno = 00:30 del 1 marzo in Italia (CET, UTC+1) -> "primavera".
        Carbon::setTestNow(Carbon::create(2026, 2, 28, 23, 30, 0, 'UTC'));

        $this->assertSame('primavera', TemporalContext::season());
    }

    public function test_now_returns_a_europe_rome_instant(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 15, 10, 0, 0, 'UTC'));

        $this->assertSame('Europe/Rome', TemporalContext::now()->getTimezone()->getName());
        $this->assertSame('12:00', TemporalContext::now()->format('H:i'));
    }

    /**
     * Bug reale trovato con una generazione vera: assegnare direttamente un Carbon con timezone
     * Europe/Rome a una colonna datetime non basta, Eloquent non lo converte da solo in fase di
     * storage — "20:35 Europe/Rome" veniva salvato e riletto come "20:35 UTC" (2 ore di
     * sfasamento in estate). todayAt() deve restituire l'istante UTC corretto.
     */
    public function test_today_at_converts_italian_time_to_the_correct_utc_instant(): void
    {
        // 15 luglio, mezzogiorno UTC = 14:00 in Italia (CEST, UTC+2) in quel momento.
        Carbon::setTestNow(Carbon::create(2026, 7, 15, 12, 0, 0, 'UTC'));

        $result = TemporalContext::todayAt('20:35');

        $this->assertSame('UTC', $result->getTimezone()->getName());
        $this->assertSame('2026-07-15 18:35:00', $result->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-15 20:35', $result->copy()->setTimezone('Europe/Rome')->format('Y-m-d H:i'));
    }

    /**
     * Algoritmo di Gauss/anonimo gregoriano — verificato contro due date reali note (11.29), non
     * inventate a mano: Pasqua 2026 = 5 aprile 2026, Pasqua 2027 = 28 marzo 2027.
     */
    public function test_easter_date_matches_known_real_dates(): void
    {
        $this->assertSame('2026-04-05', TemporalContext::easterDate(2026)->toDateString());
        $this->assertSame('2027-03-28', TemporalContext::easterDate(2027)->toDateString());
    }

    public function test_giorno_settimana_in_italian(): void
    {
        // 3 agosto 2026 è un lunedì.
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 10, 0, 0, 'UTC'));
        $this->assertSame('lunedì', TemporalContext::giornoSettimana());

        // 8 agosto 2026 è un sabato.
        Carbon::setTestNow(Carbon::create(2026, 8, 8, 10, 0, 0, 'UTC'));
        $this->assertSame('sabato', TemporalContext::giornoSettimana());
    }

    public function test_is_weekend(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 10, 0, 0, 'UTC')); // lunedì
        $this->assertFalse(TemporalContext::isWeekend());

        Carbon::setTestNow(Carbon::create(2026, 8, 8, 10, 0, 0, 'UTC')); // sabato
        $this->assertTrue(TemporalContext::isWeekend());

        Carbon::setTestNow(Carbon::create(2026, 8, 9, 10, 0, 0, 'UTC')); // domenica
        $this->assertTrue(TemporalContext::isWeekend());
    }

    public function test_festivita_on_each_fixed_date(): void
    {
        $cases = [
            ['2026-01-01', 'Capodanno'],
            ['2026-01-06', 'Epifania'],
            ['2026-04-25', 'Festa della Liberazione'],
            ['2026-05-01', 'Festa dei Lavoratori'],
            ['2026-06-02', 'Festa della Repubblica'],
            ['2026-08-15', 'Ferragosto'],
            ['2026-11-01', 'Ognissanti'],
            ['2026-12-08', 'Immacolata Concezione'],
            ['2026-12-24', 'Vigilia di Natale'],
            ['2026-12-25', 'Natale'],
            ['2026-12-26', 'Santo Stefano'],
            ['2026-12-31', 'Vigilia di Capodanno'],
        ];

        foreach ($cases as [$date, $expected]) {
            Carbon::setTestNow(Carbon::createFromFormat('Y-m-d H:i:s', "{$date} 10:00:00", 'Europe/Rome'));
            $this->assertSame($expected, TemporalContext::festivita(), "atteso '{$expected}' per {$date}");
        }
    }

    public function test_festivita_on_easter_and_easter_monday(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 5, 10, 0, 0, 'Europe/Rome'));
        $this->assertSame('Pasqua', TemporalContext::festivita());

        Carbon::setTestNow(Carbon::create(2026, 4, 6, 10, 0, 0, 'Europe/Rome'));
        $this->assertSame('Pasquetta', TemporalContext::festivita());
    }

    public function test_festivita_returns_null_on_a_regular_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 10, 0, 0, 'UTC'));

        $this->assertNull(TemporalContext::festivita());
    }
}
