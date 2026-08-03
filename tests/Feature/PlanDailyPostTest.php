<?php

namespace Tests\Feature;

use App\Console\Commands\PlanDailyPost;
use App\Models\Character;
use App\Models\Generation;
use App\Models\Tenant;
use App\Support\TemporalContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Automazione giornaliera: character:plan-daily-post deve saltare un personaggio già pianificato
 * oggi (nessuna nuova chiamata al cervello editoriale, quindi nessuna chiamata reale a OpenAI da
 * verificare qui — solo la logica di idempotenza, pura lettura DB). Bug reale che questo
 * controllo risolve: su una macchina che si sospende, un trigger a un singolo minuto esatto
 * (dailyAt) può essere perso per un'intera giornata; ricontrollare spesso "ho già pianificato
 * oggi?" rende il comando resiliente, verificato qui che l'idempotenza funzioni davvero.
 */
class PlanDailyPostTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCharacter(): Character
    {
        $tenant = Tenant::create(['name' => 'Plan Daily Test', 'email' => 'plan-daily-test-' . uniqid() . '@example.com', 'status' => 'trial']);

        return Character::create(['tenant_id' => $tenant->id, 'name' => 'Personaggio Test', 'slug' => 'personaggio-test-' . uniqid(), 'status' => 'active']);
    }

    public function test_skips_a_character_already_planned_today(): void
    {
        $character = $this->makeCharacter();

        Generation::create([
            'character_id' => $character->id,
            'tenant_id' => $character->tenant_id,
            'purpose' => 'editorial_decision',
            'status' => 'completed',
            'input' => [],
            'credits_charged' => 0,
        ]);

        $this->artisan('character:plan-daily-post', ['character' => $character->id])
            ->expectsOutputToContain('già pianificato oggi')
            ->assertExitCode(0);

        // Nessuna nuova Generation creata: il comando deve essersi fermato prima di chiamare il
        // cervello editoriale, non solo aver stampato il messaggio giusto.
        $this->assertSame(1, Generation::where('character_id', $character->id)->count());
    }

    /**
     * Verifica diretta della logica pura di alreadyPlannedToday() via reflection — evita di far
     * proseguire il comando fino a una vera chiamata GPT (fuori scope per un test automatico,
     * stessa disciplina già seguita per il resto di questa automazione) mantenendo comunque un
     * controllo reale sul confine "oggi" vs "ieri", non solo sul percorso "già pianificato".
     */
    public function test_a_generation_from_a_previous_day_does_not_count_as_planned_today(): void
    {
        $character = $this->makeCharacter();

        $yesterday = Generation::create([
            'character_id' => $character->id,
            'tenant_id' => $character->tenant_id,
            'purpose' => 'editorial_decision',
            'status' => 'completed',
            'input' => [],
            'credits_charged' => 0,
        ]);
        $yesterday->timestamps = false;
        $yesterday->created_at = TemporalContext::now()->subDay()->utc();
        $yesterday->save();

        $method = new ReflectionMethod(PlanDailyPost::class, 'alreadyPlannedToday');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(new PlanDailyPost(), $character));
    }
}
