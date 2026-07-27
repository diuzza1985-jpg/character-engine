<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterDraft;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CharacterListTest extends TestCase
{
    use DatabaseTransactions;

    public function test_lists_in_progress_drafts_and_saved_characters_with_status(): void
    {
        $tenant = Tenant::create(['name' => 'List Test', 'email' => 'list-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        CharacterDraft::create(['session_token' => 'a', 'tenant_id' => $tenant->id, 'name' => 'Bozza In Corso', 'status' => 'in_corso']);
        Character::create(['tenant_id' => $tenant->id, 'name' => 'Salvato Test', 'slug' => 'salvato-test-' . uniqid(), 'status' => 'draft']);
        Character::create(['tenant_id' => $tenant->id, 'name' => 'Attivo Test', 'slug' => 'attivo-test-' . uniqid(), 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('character.panel'));

        $response->assertOk();
        $response->assertSee('Bozza In Corso');
        $response->assertSee('Salvato Test');
        $response->assertSee('Attivo Test');
        // Le bozze (mai convertite) precedono sempre i personaggi veri, indipendentemente
        // dall'ordine relativo tra "Salvato" e "Attivo" (quello dipende solo da quale dei due
        // è stato creato per ultimo, latest('id'), non è significativo qui).
        $response->assertSeeInOrder(['Bozza In Corso', 'Personaggi']);
    }

    public function test_does_not_show_drafts_or_characters_from_another_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Mine', 'email' => 'mine@example.com', 'status' => 'trial']);
        $other = Tenant::create(['name' => 'Other', 'email' => 'other-tenant@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        Character::create(['tenant_id' => $other->id, 'name' => 'Non Mio', 'slug' => 'non-mio-' . uniqid(), 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('character.panel'));

        $response->assertOk();
        $response->assertDontSee('Non Mio');
    }

    public function test_shows_empty_state_with_no_characters(): void
    {
        $tenant = Tenant::create(['name' => 'Empty', 'email' => 'empty-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->get(route('character.panel'));

        $response->assertOk();
        $response->assertSee('Non hai ancora nessun personaggio');
    }

    public function test_requires_authentication(): void
    {
        $response = $this->get(route('character.panel'));

        $response->assertRedirect(route('login'));
    }
}
