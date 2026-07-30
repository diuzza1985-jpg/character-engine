<?php

namespace Tests\Feature;

use App\Livewire\CharacterDetail;
use App\Models\Character;
use App\Models\CharacterAsset;
use App\Models\CharacterDraft;
use App\Models\CharacterKinship;
use App\Models\CharacterRelationship;
use App\Models\CharacterVisualProfile;
use App\Models\CreditLedger;
use App\Models\SocialAccount;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConvertCharacterDraftToCharacter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        // Convenzione reale (verificata sui dati di produzione, non assunta): chi compila il
        // form dalla propria pagina pensa "io sono ___" — qui Sofia ha dichiarato "io sono
        // marito" (dato di test, non biologicamente accurato, serve solo a verificare la
        // meccanica). Sulla PROPRIA pagina (Sofia) va quindi mostrata l'etichetta invertita
        // ("moglie", ciò che Fernando è per lei); sulla pagina del personaggio collegato
        // (Fernando) va mostrato il tipo diretto ("marito", ciò che Sofia ha dichiarato di essere).
        $this->actingAs($user)->get(route('character.show', $sofia))->assertSee('moglie');
        $this->actingAs($user)->get(route('character.show', $fernando))->assertSee('marito');
    }

    /**
     * Senza questo, una parentela tra due Character già esistenti (non tramite il "mondo" di
     * qualcuno) restava invisibile al cervello editoriale di entrambi — non poteva mai essere
     * nominata nei contenuti né scelta per comparire insieme in foto (Fase 3).
     */
    public function test_adding_a_kinship_also_creates_a_structured_relationship_on_both_sides(): void
    {
        [$tenant, $user, $sofia] = $this->makeTenantUserCharacter();
        $sara = Character::create(['tenant_id' => $tenant->id, 'name' => 'Sara Test', 'slug' => 'sara-test-' . uniqid(), 'status' => 'draft']);

        // Dalla pagina di Sofia: "Sara [related] è mia figlia" (io, Sofia, sono il genitore).
        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $sofia])
            ->set('newRelatedCharacterId', (string) $sara->id)
            ->set('newRelationshipType', 'genitore')
            ->call('addKinship')
            ->assertHasNoErrors();

        // Da Sofia: Sara è sua figlia (inverso di "genitore").
        $fromSofia = CharacterRelationship::where('character_id', $sofia->id)->where('related_character_id', $sara->id)->first();
        $this->assertNotNull($fromSofia);
        $this->assertSame('figlio_a', $fromSofia->type);

        // Da Sara: Sofia è il suo genitore (diretto, ciò che Sofia ha dichiarato di essere).
        $fromSara = CharacterRelationship::where('character_id', $sara->id)->where('related_character_id', $sofia->id)->first();
        $this->assertNotNull($fromSara);
        $this->assertSame('genitore', $fromSara->type);
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

    public function test_shows_structured_questionnaire_answers_not_the_raw_bible_prose(): void
    {
        [$tenant, $user, $character] = $this->makeTenantUserCharacter();
        \App\Models\CharacterBibleSection::create(['character_id' => $character->id, 'section_key' => 'valori', 'content' => 'Testo prosa generato, non deve mai comparire qui', 'version' => 1]);
        \App\Models\CharacterDraft::create([
            'session_token' => 'x', 'tenant_id' => $tenant->id, 'character_id' => $character->id,
            'name' => 'Sofia Test', 'status' => 'convertito',
            'goal' => 'Intrattenere', 'niche' => ['Tecnologia', 'Cucina'], 'traits' => ['curioso', 'generoso'],
        ]);

        $response = $this->actingAs($user)->get(route('character.show', $character));

        // Le risposte STRUTTURATE del questionario, non la prosa generata per il motore.
        $response->assertSee('Intrattenere');
        $response->assertSee('Tecnologia, Cucina');
        $response->assertSee('curioso, generoso');
        $response->assertDontSee('Testo prosa generato, non deve mai comparire qui');
        $response->assertSee(route('character.edit', $character), false);
    }

    public function test_editing_an_animal_world_character_updates_it_and_stays_in_sync_with_the_owning_draft(): void
    {
        [$tenant, $user, $owner] = $this->makeTenantUserCharacter();
        $draft = CharacterDraft::create([
            'session_token' => 'edit-animal-' . uniqid(),
            'tenant_id' => $tenant->id,
            'character_id' => $owner->id,
            'name' => $owner->name,
            'goal' => 'Intrattenere',
            'niche' => ['Tecnologia'],
            'world_characters' => [
                ['tipo' => 'animale', 'nome' => 'Bau', 'specie' => 'cane', 'razza' => 'Meticcio', 'aspetto' => 'Pelo marrone'],
            ],
        ]);
        app(ConvertCharacterDraftToCharacter::class)->convert($draft, $tenant->id);
        $animal = Character::where('tenant_id', $tenant->id)->where('name', 'Bau')->first();

        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $animal])
            ->assertSet('editNome', 'Bau')
            ->assertSet('editSpecie', 'cane')
            ->set('editNome', 'Bau Aggiornato')
            ->set('editAspetto', 'Pelo marrone con una macchia bianca')
            ->set('editCarattere', 'Pigro')
            ->call('updateWorldCharacterFields')
            ->assertHasNoErrors();

        $animal->refresh();
        $this->assertSame('Bau Aggiornato', $animal->name);
        $this->assertSame('cane (Meticcio) — Pigro', $animal->one_liner);

        $visualProfile = CharacterVisualProfile::where('character_id', $animal->id)->first();
        $this->assertSame('Pelo marrone con una macchia bianca', $visualProfile->face_description);

        $relationship = CharacterRelationship::where('character_id', $owner->id)->where('related_character_id', $animal->id)->first();
        $this->assertSame('Bau Aggiornato', $relationship->name);

        // La voce nella draft che lo ha dichiarato deve restare sincronizzata, altrimenti un
        // futuro resave del questionario del proprietario la sovrascriverebbe con i dati vecchi.
        $draft->refresh();
        $this->assertSame('Bau Aggiornato', $draft->world_characters[0]['nome']);
        $this->assertSame('Pigro', $draft->world_characters[0]['carattere']);
    }

    public function test_editing_a_persona_world_character(): void
    {
        [$tenant, $user, $owner] = $this->makeTenantUserCharacter();
        $draft = CharacterDraft::create([
            'session_token' => 'edit-persona-' . uniqid(),
            'tenant_id' => $tenant->id,
            'character_id' => $owner->id,
            'name' => $owner->name,
            'goal' => 'Intrattenere',
            'niche' => ['Tecnologia'],
            'world_characters' => [
                ['tipo' => 'persona', 'nome' => 'Marco', 'relazione' => 'marito', 'descrizione' => 'Vecchia descrizione'],
            ],
        ]);
        app(ConvertCharacterDraftToCharacter::class)->convert($draft, $tenant->id);
        $marco = Character::where('tenant_id', $tenant->id)->where('name', 'Marco')->first();

        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $marco])
            ->assertSet('editRelazione', 'marito')
            ->set('editDescrizione', 'Nuova descrizione')
            ->call('updateWorldCharacterFields')
            ->assertHasNoErrors();

        $marco->refresh();
        $this->assertSame('Nuova descrizione', $marco->one_liner);
        $draft->refresh();
        $this->assertSame('Nuova descrizione', $draft->world_characters[0]['descrizione']);
    }

    public function test_editing_falls_back_to_a_direct_update_when_no_owning_draft_is_found(): void
    {
        [$tenant, $user, $owner] = $this->makeTenantUserCharacter();
        // Dati "orfani" deliberati: un Character con kind valorizzato ma nessuna draft del tenant
        // lo referenzia nel proprio world_characters (caso limite, non il percorso normale).
        $animal = Character::create(['tenant_id' => $tenant->id, 'name' => 'Orfano', 'kind' => 'animale', 'slug' => 'orfano-' . uniqid(), 'status' => 'draft']);
        CharacterRelationship::create(['character_id' => $owner->id, 'related_character_id' => $animal->id, 'name' => 'Orfano', 'type' => 'gatto']);

        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $animal])
            ->set('editNome', 'Orfano Rinominato')
            ->set('editSpecie', 'gatto')
            ->call('updateWorldCharacterFields')
            ->assertHasNoErrors();

        $animal->refresh();
        $this->assertSame('Orfano Rinominato', $animal->name);
        $relationship = CharacterRelationship::where('character_id', $owner->id)->where('related_character_id', $animal->id)->first();
        $this->assertSame('Orfano Rinominato', $relationship->name);
    }

    public function test_uploading_an_asset_for_an_animal_world_character_works(): void
    {
        Storage::fake('local');
        [$tenant, $user, $owner] = $this->makeTenantUserCharacter();
        $animal = Character::create(['tenant_id' => $tenant->id, 'name' => 'Fuffi', 'kind' => 'animale', 'slug' => 'fuffi-' . uniqid(), 'status' => 'draft']);

        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $animal])
            ->set('newAssetType', 'face')
            ->set('newAssetFile', UploadedFile::fake()->image('fuffi.jpg'))
            ->set('newAssetConsent', true)
            ->call('uploadAsset')
            ->assertHasNoErrors();

        $asset = CharacterAsset::where('character_id', $animal->id)->first();
        $this->assertNotNull($asset);
        Storage::disk('local')->assertExists($asset->file_path);
    }
}
