<?php

namespace Tests\Feature;

use App\Models\CreditLedger;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CharacterPanelCreditGateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_panel_shows_locked_state_when_credit_balance_is_zero(): void
    {
        $tenant = Tenant::create(['name' => 'Test', 'email' => 'panel-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        $response = $this->actingAs($user)->get(route('character.panel'));

        $response->assertOk();
        $response->assertSee('Genera il volto e i contenuti del tuo personaggio');
        $response->assertDontSee('Saldo disponibile');
    }

    public function test_panel_shows_unlocked_state_when_credit_balance_is_positive(): void
    {
        $tenant = Tenant::create(['name' => 'Test2', 'email' => 'panel-test-2@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        CreditLedger::create([
            'tenant_id' => $tenant->id,
            'amount' => 100,
            'balance_after' => 100,
            'type' => 'purchase',
        ]);

        $this->assertSame(100, $tenant->fresh()->creditBalance());

        $response = $this->actingAs($user)->get(route('character.panel'));

        $response->assertOk();
        $response->assertSee('Saldo disponibile: 100 crediti');
        $response->assertDontSee('Genera il volto e i contenuti del tuo personaggio');
    }

    public function test_panel_requires_authentication(): void
    {
        $response = $this->get(route('character.panel'));

        $response->assertRedirect(route('login'));
    }
}
