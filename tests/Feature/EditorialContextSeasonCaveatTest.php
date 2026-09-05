<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Tenant;
use App\Services\EditorialBrainService;
use App\Services\EditorialContextBuilder;
use Tests\TestCase;

/**
 * Bug reale segnalato dall'utente il 4 settembre 2026: Post#50 (generato il 2 settembre, mese di
 * calendario "autunno" secondo TemporalContext::season()) ha mostrato Sofia con un golfino pesante
 * e foglie autunnali a terra durante un'ondata di caldo reale (38°C). Nessuna integrazione meteo
 * nel codice — scelta dell'utente: non aggiungerne una ora, solo ammorbidire l'istruzione di
 * stagione nel prompt del cervello editoriale perché usi il buon senso nei periodi di passaggio
 * tra stagioni, invece di trattare il mese di calendario come una previsione meteo affidabile.
 * Solo rendering deterministico del prompt, nessuna chiamata GPT reale (stesso approccio di
 * EditorialContextDayTest).
 */
class EditorialContextSeasonCaveatTest extends TestCase
{
    private function makeCharacter(): Character
    {
        $tenant = Tenant::create(['name' => 'Season Caveat Test', 'email' => 'season-caveat-test-' . uniqid() . '@example.com', 'status' => 'trial']);

        return Character::create(['tenant_id' => $tenant->id, 'name' => 'Protagonista', 'slug' => 'protagonista-' . uniqid(), 'status' => 'active']);
    }

    public function test_the_rendered_prompt_warns_that_the_calendar_season_is_not_a_real_weather_forecast(): void
    {
        $character = $this->makeCharacter();

        $context = app(EditorialContextBuilder::class)->build($character);
        $prompt = app(EditorialBrainService::class)->lastPrompt($character, $context);

        $this->assertStringContainsString('non una previsione meteo reale', $prompt);
        $this->assertStringContainsString($context['oggi']['stagione'], $prompt);
    }
}
