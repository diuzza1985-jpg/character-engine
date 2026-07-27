<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DashboardMenuTest extends TestCase
{
    use DatabaseTransactions;

    public function test_authenticated_pages_show_the_lateral_menu(): void
    {
        $tenant = Tenant::create(['name' => 'Menu Test', 'email' => 'menu-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->get(route('character.panel'));

        $response->assertOk();
        $response->assertSee('Personaggi');
        $response->assertSee('Acquista crediti');
        $response->assertSee($user->email);
        $response->assertSee(route('logout'), false);
    }

    public function test_logout_ends_the_session_and_redirects_home(): void
    {
        $tenant = Tenant::create(['name' => 'Logout Test', 'email' => 'logout-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_credits_placeholder_page_requires_authentication(): void
    {
        $response = $this->get(route('credits.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_credits_placeholder_page_shows_coming_soon(): void
    {
        $tenant = Tenant::create(['name' => 'Credits Test', 'email' => 'credits-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->get(route('credits.index'));

        $response->assertOk();
        $response->assertSee('In arrivo');
    }
}
