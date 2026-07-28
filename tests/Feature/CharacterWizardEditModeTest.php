<?php

namespace Tests\Feature;

use App\Livewire\CharacterCreationWizard;
use App\Models\Character;
use App\Models\CharacterBibleSection;
use App\Models\CharacterDraft;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CharacterWizardEditModeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_approfondimento_step_is_absent_for_anonymous_users(): void
    {
        Livewire::test(CharacterCreationWizard::class)
            ->call('nextStep', 'why', 'identity', ['goal' => 'Educare', 'niche' => ['Salute', 'Sport']])
            ->call('nextStep', 'identity', 'personality', ['name' => 'Anon', 'oneLiner' => 'Test'])
            ->call('nextStep', 'personality', 'voice', ['traits' => ['curioso', 'calmo', 'generoso', 'pragmatico'], 'coreValues' => ['Libertà', 'Creatività'], 'dislikes' => ['Ritardi', 'Rumore']])
            ->call('nextStep', 'voice', 'humor', ['communicationFormality' => 50, 'communicationVerbosity' => 50, 'communicationDirectness' => 50, 'emojiUsage' => 'raramente'])
            ->call('nextStep', 'humor', 'appearance', ['humorLevel' => 'mai', 'jokeTargets' => []])
            ->call('nextStep', 'appearance', 'summary', ['ageRange' => '26-35', 'presentation' => 'Femminile', 'styleArchetype' => 'Boho', 'hairLength' => 'Medi'])
            ->assertSet('step', 'summary'); // salta approfondimento, va dritto a summary
    }

    public function test_approfondimento_step_is_absent_even_for_authenticated_users_during_creation(): void
    {
        // Corretto dopo revisione: Approfondimento è un arricchimento solo per la modifica di un
        // personaggio già salvato, mai per la creazione — nemmeno se già loggati. Verifica il
        // markup renderizzato (la destinazione reale del bottone "Avanti"), non solo che
        // nextStep() accetti comunque una chiamata diretta a 'approfondimento'.
        $tenant = Tenant::create(['name' => 'Approf Test', 'email' => 'approf-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        $component = Livewire::actingAs($user)->test(CharacterCreationWizard::class)->set('step', 'appearance');

        $component->assertDontSeeHtml("nextStep('appearance', 'approfondimento'");
        $component->assertSeeHtml("nextStep('appearance', 'summary'");
    }

    public function test_approfondimento_step_is_reachable_only_in_edit_mode(): void
    {
        $tenant = Tenant::create(['name' => 'Approf Edit', 'email' => 'approf-edit@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Da Approfondire', 'one_liner' => 'Test', 'slug' => 'da-approfondire-' . uniqid(), 'status' => 'draft']);
        CharacterDraft::create(['session_token' => 'approf-edit-' . uniqid(), 'tenant_id' => $tenant->id, 'character_id' => $character->id, 'name' => 'Da Approfondire', 'goal' => 'Educare', 'niche' => ['Salute'], 'status' => 'convertito']);

        $component = Livewire::actingAs($user)->test(CharacterCreationWizard::class, ['character' => $character])
            ->set('step', 'appearance');

        $component->assertSeeHtml("nextStep('appearance', 'approfondimento'");

        $component->call('nextStep', 'appearance', 'approfondimento', ['ageRange' => '26-35', 'presentation' => 'Femminile', 'styleArchetype' => 'Boho', 'hairLength' => 'Medi'])
            ->assertSet('step', 'approfondimento');
    }

    public function test_approfondimento_data_is_persisted_and_feeds_biografia_section_when_editing(): void
    {
        $tenant = Tenant::create(['name' => 'Approf Persist', 'email' => 'approf-persist@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Bio Test', 'one_liner' => 'Test', 'slug' => 'bio-test-' . uniqid(), 'status' => 'draft']);
        CharacterDraft::create([
            'session_token' => 'approf-persist-' . uniqid(), 'tenant_id' => $tenant->id, 'character_id' => $character->id,
            'name' => 'Bio Test', 'goal' => 'Educare', 'niche' => ['Salute', 'Sport'], 'status' => 'convertito',
        ]);

        Livewire::actingAs($user)->test(CharacterCreationWizard::class, ['character' => $character])
            ->call('nextStep', 'why', 'identity', ['goal' => 'Educare', 'niche' => ['Salute', 'Sport']])
            ->call('nextStep', 'identity', 'personality', ['name' => 'Bio Test', 'oneLiner' => 'Frase aggiornata'])
            ->call('nextStep', 'personality', 'voice', ['traits' => ['curioso', 'calmo', 'generoso', 'pragmatico'], 'coreValues' => ['Libertà', 'Creatività'], 'dislikes' => ['Ritardi', 'Rumore']])
            ->call('nextStep', 'voice', 'humor', ['communicationFormality' => 50, 'communicationVerbosity' => 50, 'communicationDirectness' => 50, 'emojiUsage' => 'raramente'])
            ->call('nextStep', 'humor', 'appearance', ['humorLevel' => 'mai', 'jokeTargets' => []])
            ->call('nextStep', 'appearance', 'approfondimento', ['ageRange' => '26-35', 'presentation' => 'Femminile', 'styleArchetype' => 'Boho', 'hairLength' => 'Medi'])
            ->call('nextStep', 'approfondimento', 'summary', [
                'backstory' => 'Cresciuta in campagna.',
                'keyRelationships' => [['nome' => 'Fernando', 'relazione' => 'marito', 'tratto' => 'sempre indaffarato']],
            ])
            ->call('saveAndContinue')
            ->assertRedirect(route('character.panel'));

        $character->refresh();
        $this->assertSame('Frase aggiornata', $character->one_liner);
        $bio = CharacterBibleSection::where('character_id', $character->id)->where('section_key', 'biografia')->first();
        $this->assertStringContainsString('Cresciuta in campagna', $bio->content);
        $this->assertSame(1, \App\Models\CharacterRelationship::where('character_id', $character->id)->where('name', 'Fernando')->count());
    }

    public function test_edit_route_prefills_the_wizard_from_the_linked_draft(): void
    {
        $tenant = Tenant::create(['name' => 'Edit Test', 'email' => 'edit-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Personaggio Edit', 'one_liner' => 'Frase originale', 'slug' => 'personaggio-edit-' . uniqid(), 'status' => 'draft']);
        $draft = CharacterDraft::create([
            'session_token' => 'edit-mode-' . uniqid(),
            'tenant_id' => $tenant->id,
            'character_id' => $character->id,
            'name' => 'Personaggio Edit',
            'one_liner' => 'Frase originale',
            'goal' => 'Intrattenere',
            'niche' => ['Tecnologia'],
            'status' => 'convertito',
        ]);

        $component = Livewire::actingAs($user)->test(CharacterCreationWizard::class, ['character' => $character]);

        $component->assertSet('step', 'why')
            ->assertSet('name', 'Personaggio Edit')
            ->assertSet('oneLiner', 'Frase originale')
            ->assertSet('goal', 'Intrattenere')
            ->assertSet('draftId', $draft->id);
    }

    public function test_saving_edit_mode_updates_the_existing_character_in_place(): void
    {
        $tenant = Tenant::create(['name' => 'Edit Save Test', 'email' => 'edit-save-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Da Modificare', 'one_liner' => 'Vecchia frase', 'slug' => 'da-modificare-' . uniqid(), 'status' => 'draft']);
        CharacterDraft::create([
            'session_token' => 'edit-save-' . uniqid(),
            'tenant_id' => $tenant->id,
            'character_id' => $character->id,
            'name' => 'Da Modificare',
            'one_liner' => 'Vecchia frase',
            'goal' => 'Intrattenere',
            'niche' => ['Tecnologia'],
            'status' => 'convertito',
        ]);

        Livewire::actingAs($user)->test(CharacterCreationWizard::class, ['character' => $character])
            ->call('nextStep', 'why', 'identity', ['goal' => 'Intrattenere', 'niche' => ['Tecnologia', 'Cucina']])
            ->set('name', 'Da Modificare')
            ->set('oneLiner', 'Nuova frase aggiornata')
            ->call('saveAndContinue')
            ->assertRedirect(route('character.panel'));

        $character->refresh();
        $this->assertSame('Nuova frase aggiornata', $character->one_liner);
        $this->assertSame(1, Character::where('tenant_id', $tenant->id)->count(), 'non deve creare un secondo Character');
    }

    public function test_editing_a_character_without_a_linked_draft_is_blocked(): void
    {
        $tenant = Tenant::create(['name' => 'No Draft Test', 'email' => 'no-draft-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        // Personaggio "legacy", creato senza passare dal questionario (come Sofia via admin).
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Legacy', 'slug' => 'legacy-' . uniqid(), 'status' => 'active']);

        $response = $this->actingAs($user)->get(route('character.edit', $character));

        $response->assertNotFound();
    }

    public function test_edit_route_requires_authentication(): void
    {
        $tenant = Tenant::create(['name' => 'Auth Required Test', 'email' => 'auth-required-test@example.com', 'status' => 'trial']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Prot', 'slug' => 'prot-' . uniqid(), 'status' => 'draft']);

        $response = $this->get(route('character.edit', $character));

        $response->assertRedirect(route('login'));
    }
}
