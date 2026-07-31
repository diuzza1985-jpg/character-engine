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
}
