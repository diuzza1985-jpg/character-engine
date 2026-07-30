<?php

namespace Tests\Feature;

use App\Livewire\CharacterDetail;
use App\Models\Character;
use App\Models\CharacterDraft;
use App\Models\CharacterKinship;
use App\Models\CharacterRelationship;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ConvertCharacterDraftToCharacter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class WorldCharactersHouseholdPropagationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_an_animal_declared_by_a_child_is_also_linked_to_both_parents(): void
    {
        $tenant = Tenant::create(['name' => 'Household Test', 'email' => 'household-test@example.com', 'status' => 'trial']);
        $parentA = Character::create(['tenant_id' => $tenant->id, 'name' => 'Genitore A', 'slug' => 'genitore-a-' . uniqid(), 'status' => 'active']);
        $parentB = Character::create(['tenant_id' => $tenant->id, 'name' => 'Genitore B', 'slug' => 'genitore-b-' . uniqid(), 'status' => 'draft']);
        $child = Character::create(['tenant_id' => $tenant->id, 'name' => 'Figlio', 'slug' => 'figlio-' . uniqid(), 'status' => 'draft']);

        // Parentela registrata dal lato del figlio (figlia/o -> genitore), come farebbe l'utente
        // dalla pagina "Parentele" del figlio.
        CharacterKinship::create(['character_id' => $child->id, 'related_character_id' => $parentA->id, 'relationship_type' => 'figlio']);
        // Parentela registrata dal lato opposto (genitore -> figlio), per verificare che la
        // propagazione funzioni indipendentemente da chi l'ha registrata per prima.
        CharacterKinship::create(['character_id' => $parentB->id, 'related_character_id' => $child->id, 'relationship_type' => 'genitore']);

        $draft = CharacterDraft::create([
            'session_token' => 'household-' . uniqid(),
            'tenant_id' => $tenant->id,
            'character_id' => $child->id,
            'name' => 'Figlio',
            'goal' => 'Intrattenere',
            'niche' => ['Tecnologia'],
            'world_characters' => [
                ['tipo' => 'animale', 'nome' => 'Bau', 'specie' => 'cane', 'razza' => 'Meticcio', 'aspetto' => 'Pelo marrone', 'carattere' => 'giocherellone'],
            ],
        ]);

        app(ConvertCharacterDraftToCharacter::class)->convert($draft, $tenant->id);

        $animal = Character::where('tenant_id', $tenant->id)->where('name', 'Bau')->first();
        $this->assertNotNull($animal);
        $this->assertSame('cane (Meticcio) — giocherellone', $animal->one_liner);

        foreach ([$child, $parentA, $parentB] as $owner) {
            $relationship = CharacterRelationship::where('character_id', $owner->id)->where('related_character_id', $animal->id)->first();
            $this->assertNotNull($relationship, "{$owner->name} dovrebbe avere una relazione con Bau");
            $this->assertSame('Bau', $relationship->name);
            $this->assertSame('cane', $relationship->type);
        }

        // Nessuna CharacterKinship creata per l'animale: solo per le persone (principio già stabilito in Fase 2).
        $this->assertSame(0, CharacterKinship::where('related_character_id', $animal->id)->count());
    }

    public function test_a_secondary_person_is_not_propagated_to_the_household(): void
    {
        $tenant = Tenant::create(['name' => 'No Propagation Test', 'email' => 'no-propagation-test@example.com', 'status' => 'trial']);
        $parent = Character::create(['tenant_id' => $tenant->id, 'name' => 'Genitore', 'slug' => 'genitore-' . uniqid(), 'status' => 'active']);
        $child = Character::create(['tenant_id' => $tenant->id, 'name' => 'Figlio', 'slug' => 'figlio-' . uniqid(), 'status' => 'draft']);

        CharacterKinship::create(['character_id' => $child->id, 'related_character_id' => $parent->id, 'relationship_type' => 'figlio']);

        $draft = CharacterDraft::create([
            'session_token' => 'no-propagation-' . uniqid(),
            'tenant_id' => $tenant->id,
            'character_id' => $child->id,
            'name' => 'Figlio',
            'goal' => 'Intrattenere',
            'niche' => ['Tecnologia'],
            'world_characters' => [
                ['tipo' => 'persona', 'nome' => 'Amico', 'relazione' => 'amico_a', 'descrizione' => 'Un amico stretto'],
            ],
        ]);

        app(ConvertCharacterDraftToCharacter::class)->convert($draft, $tenant->id);

        $friend = Character::where('tenant_id', $tenant->id)->where('name', 'Amico')->first();
        $this->assertNotNull($friend);

        $this->assertNotNull(CharacterRelationship::where('character_id', $child->id)->where('related_character_id', $friend->id)->first());
        $this->assertNull(CharacterRelationship::where('character_id', $parent->id)->where('related_character_id', $friend->id)->first(), 'una persona secondaria non deve propagarsi al resto della famiglia');
    }

    public function test_reconverting_does_not_duplicate_propagated_relationships(): void
    {
        $tenant = Tenant::create(['name' => 'Idempotent Test', 'email' => 'idempotent-test@example.com', 'status' => 'trial']);
        $parent = Character::create(['tenant_id' => $tenant->id, 'name' => 'Genitore', 'slug' => 'genitore-' . uniqid(), 'status' => 'active']);
        $child = Character::create(['tenant_id' => $tenant->id, 'name' => 'Figlio', 'slug' => 'figlio-' . uniqid(), 'status' => 'draft']);

        CharacterKinship::create(['character_id' => $child->id, 'related_character_id' => $parent->id, 'relationship_type' => 'figlio']);

        $draft = CharacterDraft::create([
            'session_token' => 'idempotent-' . uniqid(),
            'tenant_id' => $tenant->id,
            'character_id' => $child->id,
            'name' => 'Figlio',
            'goal' => 'Intrattenere',
            'niche' => ['Tecnologia'],
            'world_characters' => [
                ['tipo' => 'animale', 'nome' => 'Bau', 'specie' => 'cane'],
            ],
        ]);

        $converter = app(ConvertCharacterDraftToCharacter::class);
        $converter->convert($draft, $tenant->id);
        $converter->convert($draft->fresh(), $tenant->id);

        $animal = Character::where('tenant_id', $tenant->id)->where('name', 'Bau')->first();
        $this->assertSame(1, CharacterRelationship::where('character_id', $parent->id)->where('related_character_id', $animal->id)->count());
    }

    /**
     * Il limite segnalato dall'utente: prima serviva riaprire e risalvare il questionario perché
     * un animale già dichiarato raggiungesse un parente collegato DOPO. Ora la propagazione parte
     * subito da CharacterDetail::addKinship(), senza toccare il questionario.
     */
    public function test_adding_a_kinship_retroactively_propagates_already_declared_animals(): void
    {
        $tenant = Tenant::create(['name' => 'Retroactive Test', 'email' => 'retroactive-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $parent = Character::create(['tenant_id' => $tenant->id, 'name' => 'Genitore', 'slug' => 'genitore-' . uniqid(), 'status' => 'active']);
        $child = Character::create(['tenant_id' => $tenant->id, 'name' => 'Figlio', 'slug' => 'figlio-' . uniqid(), 'status' => 'draft']);

        // Il figlio dichiara un animale PRIMA che esista qualunque parentela.
        $draft = CharacterDraft::create([
            'session_token' => 'retroactive-' . uniqid(),
            'tenant_id' => $tenant->id,
            'character_id' => $child->id,
            'name' => 'Figlio',
            'goal' => 'Intrattenere',
            'niche' => ['Tecnologia'],
            'world_characters' => [
                ['tipo' => 'animale', 'nome' => 'Bau', 'specie' => 'cane'],
            ],
        ]);
        app(ConvertCharacterDraftToCharacter::class)->convert($draft, $tenant->id);
        $animal = Character::where('tenant_id', $tenant->id)->where('name', 'Bau')->first();

        $this->assertNull(CharacterRelationship::where('character_id', $parent->id)->where('related_character_id', $animal->id)->first(), 'ancora nessuna parentela, nessuna propagazione attesa a questo punto');

        // La parentela viene aggiunta DOPO, dalla pagina del genitore — nessun questionario riaperto.
        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $parent])
            ->set('newRelatedCharacterId', (string) $child->id)
            ->set('newRelationshipType', 'genitore')
            ->call('addKinship');

        $relationship = CharacterRelationship::where('character_id', $parent->id)->where('related_character_id', $animal->id)->first();
        $this->assertNotNull($relationship, 'il genitore deve avere subito la relazione con Bau, senza riaprire il questionario');
        $this->assertSame('Bau', $relationship->name);
        $this->assertSame('cane', $relationship->type);
    }
}
