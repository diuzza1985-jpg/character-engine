<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Tenant;
use App\Services\EditorialBrainService;
use App\Services\EditorialContextBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Bug reale segnalato dall'utente (11.29): il cervello editoriale sapeva solo ora/stagione, mai
 * che giorno della settimana fosse — poteva quindi scrivere un contenuto "giornata di lavoro/
 * ufficio" anche di sabato, domenica o a Natale. Questi test verificano solo la parte
 * deterministica (context builder + rendering del prompt), nessuna chiamata GPT reale.
 */
class EditorialContextDayTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeCharacter(): Character
    {
        $tenant = Tenant::create(['name' => 'Day Context Test', 'email' => 'day-context-test-' . uniqid() . '@example.com', 'status' => 'trial']);

        return Character::create(['tenant_id' => $tenant->id, 'name' => 'Protagonista', 'slug' => 'protagonista-' . uniqid(), 'status' => 'active']);
    }

    public function test_context_includes_weekday_weekend_and_holiday_on_christmas(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 12, 25, 10, 0, 0, 'Europe/Rome')); // venerdì
        $character = $this->makeCharacter();

        $context = app(EditorialContextBuilder::class)->build($character);

        $this->assertSame('venerdì', $context['oggi']['giorno_settimana']);
        $this->assertFalse($context['oggi']['weekend']);
        $this->assertSame('Natale', $context['oggi']['festivita']);
    }

    public function test_the_rendered_prompt_shows_the_day_context_block_on_christmas(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 12, 25, 10, 0, 0, 'Europe/Rome'));
        $character = $this->makeCharacter();

        $context = app(EditorialContextBuilder::class)->build($character);
        $prompt = app(EditorialBrainService::class)->lastPrompt($character, $context);

        $this->assertStringContainsString('CONTESTO DEL GIORNO', $prompt);
        $this->assertStringContainsString('venerdì, Natale', $prompt);
        $this->assertStringContainsString($character->name, $prompt);
    }

    public function test_the_rendered_prompt_shows_the_day_context_block_on_a_sunday_with_no_holiday(): void
    {
        // 9 agosto 2026 è una domenica, nessuna festività.
        Carbon::setTestNow(Carbon::create(2026, 8, 9, 10, 0, 0, 'Europe/Rome'));
        $character = $this->makeCharacter();

        $context = app(EditorialContextBuilder::class)->build($character);
        $prompt = app(EditorialBrainService::class)->lastPrompt($character, $context);

        $this->assertTrue($context['oggi']['weekend']);
        $this->assertNull($context['oggi']['festivita']);
        $this->assertStringContainsString('CONTESTO DEL GIORNO', $prompt);
        $this->assertStringContainsString('Oggi è domenica.', $prompt);
    }

    public function test_the_rendered_prompt_omits_the_day_context_block_on_a_regular_weekday(): void
    {
        // 3 agosto 2026 è un lunedì, nessuna festività.
        Carbon::setTestNow(Carbon::create(2026, 8, 3, 10, 0, 0, 'Europe/Rome'));
        $character = $this->makeCharacter();

        $context = app(EditorialContextBuilder::class)->build($character);
        $prompt = app(EditorialBrainService::class)->lastPrompt($character, $context);

        $this->assertFalse($context['oggi']['weekend']);
        $this->assertNull($context['oggi']['festivita']);
        $this->assertStringNotContainsString('CONTESTO DEL GIORNO', $prompt);
    }
}
