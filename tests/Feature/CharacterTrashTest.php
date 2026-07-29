<?php

namespace Tests\Feature;

use App\Livewire\CharacterTrash;
use App\Models\Character;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CharacterTrashTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTrashedCharacter(Tenant $tenant, string $name = 'Eliminato Test'): Character
    {
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => $name, 'slug' => Str::slug($name) . '-' . uniqid(), 'status' => 'draft']);
        $character->delete();

        return $character;
    }

    public function test_lists_only_soft_deleted_characters_of_the_current_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Trash List', 'email' => 'trash-list@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $trashed = $this->makeTrashedCharacter($tenant, 'Nel Cestino');
        Character::create(['tenant_id' => $tenant->id, 'name' => 'Non Nel Cestino', 'slug' => 'non-nel-cestino-' . uniqid(), 'status' => 'draft']);

        $response = $this->actingAs($user)->get(route('character.trash'));

        $response->assertOk();
        $response->assertSee('Nel Cestino');
        $response->assertDontSee('Non Nel Cestino');
    }

    public function test_does_not_show_trashed_characters_from_another_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Trash Mine', 'email' => 'trash-mine@example.com', 'status' => 'trial']);
        $other = Tenant::create(['name' => 'Trash Other', 'email' => 'trash-other@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->makeTrashedCharacter($other, 'Cestino Altrui');

        $response = $this->actingAs($user)->get(route('character.trash'));

        $response->assertOk();
        $response->assertDontSee('Cestino Altrui');
    }

    public function test_restore_brings_the_character_back_to_the_main_list(): void
    {
        $tenant = Tenant::create(['name' => 'Restore Test', 'email' => 'restore-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $character = $this->makeTrashedCharacter($tenant, 'Da Ripristinare');

        Livewire::actingAs($user)->test(CharacterTrash::class)
            ->call('restore', $character->id);

        $this->assertNotSoftDeleted($character);

        $response = $this->actingAs($user)->get(route('character.panel'));
        $response->assertSee('Da Ripristinare');
    }

    public function test_cannot_restore_a_character_belonging_to_another_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Restore Mine', 'email' => 'restore-mine@example.com', 'status' => 'trial']);
        $other = Tenant::create(['name' => 'Restore Other', 'email' => 'restore-other@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $character = $this->makeTrashedCharacter($other, 'Non Mio Trashed');

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)->test(CharacterTrash::class)
            ->call('restore', $character->id);
    }

    public function test_force_delete_permanently_removes_the_character_and_it_cannot_be_restored(): void
    {
        $tenant = Tenant::create(['name' => 'Force Delete', 'email' => 'force-delete@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $character = $this->makeTrashedCharacter($tenant, 'Da Cancellare Per Sempre');
        $characterId = $character->id;

        Livewire::actingAs($user)->test(CharacterTrash::class)
            ->call('forceDelete', $characterId);

        $this->assertDatabaseMissing('characters', ['id' => $characterId]);
    }

    public function test_cannot_force_delete_a_character_belonging_to_another_tenant(): void
    {
        $tenant = Tenant::create(['name' => 'Force Mine', 'email' => 'force-mine@example.com', 'status' => 'trial']);
        $other = Tenant::create(['name' => 'Force Other', 'email' => 'force-other@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $character = $this->makeTrashedCharacter($other, 'Non Mio Force');

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($user)->test(CharacterTrash::class)
            ->call('forceDelete', $character->id);
    }

    public function test_shows_empty_state_with_nothing_in_the_trash(): void
    {
        $tenant = Tenant::create(['name' => 'Trash Empty', 'email' => 'trash-empty@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($user)->get(route('character.trash'));

        $response->assertOk();
        $response->assertSee('Il cestino è vuoto');
    }

    public function test_requires_authentication(): void
    {
        $response = $this->get(route('character.trash'));

        $response->assertRedirect(route('login'));
    }
}
