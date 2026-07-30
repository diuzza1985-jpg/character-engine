<?php

namespace Tests\Feature;

use App\Livewire\CharacterList;
use App\Models\Character;
use App\Models\CharacterDraft;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
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

    public function test_shows_a_kind_badge_for_world_characters(): void
    {
        $tenant = Tenant::create(['name' => 'Kind Badge Test', 'email' => 'kind-badge-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        Character::create(['tenant_id' => $tenant->id, 'name' => 'Principale', 'slug' => 'principale-' . uniqid(), 'status' => 'active']);
        Character::create(['tenant_id' => $tenant->id, 'name' => 'Bau', 'kind' => 'animale', 'slug' => 'bau-' . uniqid(), 'status' => 'draft']);
        Character::create(['tenant_id' => $tenant->id, 'name' => 'Marco', 'kind' => 'persona', 'slug' => 'marco-' . uniqid(), 'status' => 'draft']);

        $response = $this->actingAs($user)->get(route('character.panel'));

        $response->assertSee('🐾 animale');
        $response->assertSee('🧑 persona');
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

    public function test_deleting_a_character_soft_deletes_it_and_removes_it_from_the_list(): void
    {
        $tenant = Tenant::create(['name' => 'Delete Test', 'email' => 'delete-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Da Eliminare', 'slug' => 'da-eliminare-' . uniqid(), 'status' => 'draft']);

        Livewire::actingAs($user)->test(CharacterList::class)
            ->call('deleteCharacter', $character->id);

        $this->assertSoftDeleted($character);

        $response = $this->actingAs($user)->get(route('character.panel'));
        $response->assertDontSee('Da Eliminare');
    }

    public function test_cannot_delete_a_character_belonging_to_another_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Mine Delete', 'email' => 'mine-delete@example.com', 'status' => 'trial']);
        $other = Tenant::create(['name' => 'Other Delete', 'email' => 'other-delete@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $character = Character::create(['tenant_id' => $other->id, 'name' => 'Non Mio', 'slug' => 'non-mio-elim-' . uniqid(), 'status' => 'draft']);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)->test(CharacterList::class)
            ->call('deleteCharacter', $character->id);
    }

    public function test_deleting_a_draft_physically_removes_it_no_trash(): void
    {
        $tenant = Tenant::create(['name' => 'Delete Draft', 'email' => 'delete-draft@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $draft = CharacterDraft::create(['session_token' => 'a', 'tenant_id' => $tenant->id, 'name' => 'Bozza Da Eliminare', 'status' => 'in_corso']);

        Livewire::actingAs($user)->test(CharacterList::class)
            ->call('deleteDraft', $draft->id);

        $this->assertDatabaseMissing('character_drafts', ['id' => $draft->id]);

        $response = $this->actingAs($user)->get(route('character.panel'));
        $response->assertDontSee('Bozza Da Eliminare');
    }

    public function test_cannot_delete_a_draft_belonging_to_another_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Mine Draft', 'email' => 'mine-draft@example.com', 'status' => 'trial']);
        $other = Tenant::create(['name' => 'Other Draft', 'email' => 'other-draft@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $draft = CharacterDraft::create(['session_token' => 'b', 'tenant_id' => $other->id, 'name' => 'Non Mia', 'status' => 'in_corso']);

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)->test(CharacterList::class)
            ->call('deleteDraft', $draft->id);
    }
}
