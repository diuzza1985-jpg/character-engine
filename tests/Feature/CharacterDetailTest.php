<?php

namespace Tests\Feature;

use App\Livewire\CharacterDetail;
use App\Models\Character;
use App\Models\CharacterKinship;
use App\Models\CreditLedger;
use App\Models\SocialAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CharacterDetailTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenantUserCharacter(): array
    {
        $tenant = Tenant::create(['name' => 'Detail Test', 'email' => 'detail-test-' . uniqid() . '@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Sofia Test', 'one_liner' => 'Una sviluppatrice', 'slug' => 'sofia-test-' . uniqid(), 'status' => 'draft']);

        return [$tenant, $user, $character];
    }

    public function test_cannot_view_a_character_belonging_to_another_tenant(): void
    {
        [, , $character] = $this->makeTenantUserCharacter();
        $otherUser = User::factory()->create(['tenant_id' => Tenant::create(['name' => 'Altro', 'email' => 'altro-tenant@example.com', 'status' => 'trial'])->id]);

        $response = $this->actingAs($otherUser)->get(route('character.show', $character));

        $response->assertForbidden();
    }

    public function test_activation_is_blocked_without_a_connected_instagram_account(): void
    {
        [, $user, $character] = $this->makeTenantUserCharacter();

        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $character])
            ->call('toggleActive')
            ->assertHasErrors('activation');

        $this->assertSame('draft', $character->fresh()->status);
    }

    public function test_activation_succeeds_with_a_connected_instagram_account(): void
    {
        [, $user, $character] = $this->makeTenantUserCharacter();
        SocialAccount::create(['character_id' => $character->id, 'platform' => 'instagram', 'ig_user_id' => '123', 'status' => 'connected']);

        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $character])
            ->call('toggleActive')
            ->assertHasNoErrors();

        $this->assertSame('active', $character->fresh()->status);
    }

    public function test_toggle_active_pauses_an_already_active_character_without_needing_instagram(): void
    {
        [, $user, $character] = $this->makeTenantUserCharacter();
        $character->update(['status' => 'active']);

        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $character])
            ->call('toggleActive')
            ->assertHasNoErrors();

        $this->assertSame('draft', $character->fresh()->status);
    }

    public function test_adding_a_kinship_shows_up_on_both_characters_with_inverse_label(): void
    {
        [$tenant, $user, $sofia] = $this->makeTenantUserCharacter();
        $fernando = Character::create(['tenant_id' => $tenant->id, 'name' => 'Fernando Test', 'slug' => 'fernando-test-' . uniqid(), 'status' => 'draft']);

        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $sofia])
            ->set('newRelatedCharacterId', (string) $fernando->id)
            ->set('newRelationshipType', 'marito')
            ->call('addKinship')
            ->assertHasNoErrors();

        $this->assertSame(1, CharacterKinship::where('character_id', $sofia->id)->where('related_character_id', $fernando->id)->count());

        // Vista da Sofia: vede "marito" (diretto). Vista da Fernando: vede "moglie" (inverso).
        $this->actingAs($user)->get(route('character.show', $sofia))->assertSee('marito');
        $this->actingAs($user)->get(route('character.show', $fernando))->assertSee('moglie');
    }

    public function test_removing_a_kinship(): void
    {
        [$tenant, $user, $sofia] = $this->makeTenantUserCharacter();
        $fernando = Character::create(['tenant_id' => $tenant->id, 'name' => 'Fernando Rm', 'slug' => 'fernando-rm-' . uniqid(), 'status' => 'draft']);
        $kinship = CharacterKinship::create(['character_id' => $sofia->id, 'related_character_id' => $fernando->id, 'relationship_type' => 'marito']);

        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $sofia])
            ->call('removeKinship', $kinship->id)
            ->assertHasNoErrors();

        $this->assertSame(0, CharacterKinship::where('id', $kinship->id)->count());
    }

    public function test_rejects_an_invalid_relationship_type(): void
    {
        [$tenant, $user, $sofia] = $this->makeTenantUserCharacter();
        $fernando = Character::create(['tenant_id' => $tenant->id, 'name' => 'Fernando Invalid', 'slug' => 'fernando-invalid-' . uniqid(), 'status' => 'draft']);

        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $sofia])
            ->set('newRelatedCharacterId', (string) $fernando->id)
            ->set('newRelationshipType', 'un-tipo-inventato')
            ->call('addKinship')
            ->assertHasErrors('newRelationshipType');
    }

    public function test_credit_gate_reflects_real_balance(): void
    {
        [$tenant, $user, $character] = $this->makeTenantUserCharacter();

        $response = $this->actingAs($user)->get(route('character.show', $character));
        $response->assertSee('Genera il volto e i contenuti del tuo personaggio');

        CreditLedger::create(['tenant_id' => $tenant->id, 'amount' => 50, 'balance_after' => 50, 'type' => 'purchase']);

        $response = $this->actingAs($user)->get(route('character.show', $character));
        $response->assertSee('Saldo disponibile: 50 crediti');
    }

    public function test_shows_bible_sections_read_only_and_edit_link_when_draft_exists(): void
    {
        [$tenant, $user, $character] = $this->makeTenantUserCharacter();
        \App\Models\CharacterBibleSection::create(['character_id' => $character->id, 'section_key' => 'valori', 'content' => 'Contenuto di prova', 'version' => 1]);
        \App\Models\CharacterDraft::create(['session_token' => 'x', 'tenant_id' => $tenant->id, 'character_id' => $character->id, 'name' => 'Sofia Test', 'status' => 'convertito']);

        $response = $this->actingAs($user)->get(route('character.show', $character));

        $response->assertSee('Contenuto di prova');
        $response->assertSee(route('character.edit', $character), false);
    }
}
