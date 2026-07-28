<?php

namespace Tests\Feature;

use App\Livewire\CharacterCreationWizard;
use App\Models\CharacterDraft;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CharacterCreationWizardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_happy_path_creates_and_persists_a_draft_through_every_step(): void
    {
        $test = Livewire::test(CharacterCreationWizard::class);

        $test->assertSet('step', 'intro')
            ->call('$set', 'step', 'why')
            ->assertSet('step', 'why');

        $test->call('nextStep', 'why', 'identity', [
            'goal' => 'Intrattenere',
            'goalSecondary' => null,
            'targetAudience' => ['Studenti'],
            'niche' => ['Tecnologia', 'Cucina'],
        ])->assertSet('step', 'identity')->assertHasNoErrors();

        $test->call('nextStep', 'identity', 'personality', [
            'name' => 'Test Character',
            'role' => 'Sviluppatrice',
            'oneLiner' => 'Vive di caffè e debug notturni',
            'livingSituation' => 'Da solo/a',
            'pets' => ['Gatto'],
            'environment' => 'Città',
        ])->assertSet('step', 'personality')->assertHasNoErrors();

        $test->call('nextStep', 'personality', 'voice', [
            'traits' => ['curioso', 'pragmatico', 'calmo', 'generoso'],
            'coreValues' => ['Libertà', 'Creatività'],
            'dislikes' => ['Ritardi', 'Ipocrisia'],
        ])->assertSet('step', 'voice')->assertHasNoErrors();

        $test->call('nextStep', 'voice', 'humor', [
            'communicationFormality' => 30,
            'communicationVerbosity' => 60,
            'communicationDirectness' => 70,
            'emojiUsage' => 'raramente',
        ])->assertSet('step', 'humor')->assertHasNoErrors();

        $test->call('nextStep', 'humor', 'appearance', [
            'humorLevel' => 'leggero',
            'jokeTargets' => ['Lavoro', 'Tecnologia', 'Traffico', 'Cibo', 'Burocrazia'],
            'humorSafeTopics' => ['Aspetto fisico', 'Salute / malattie', 'Salute mentale', 'Difficoltà economiche', 'Lutti e tragedie'],
        ])->assertSet('step', 'appearance')->assertHasNoErrors();

        $test->call('nextStep', 'appearance', 'summary', [
            'ageRange' => '26-35',
            'presentation' => 'Femminile',
            'styleArchetype' => 'Casual sportivo',
            'hairColor' => 'Castano',
            'hairLength' => 'Medi',
            'hairStyle' => 'Ricci',
            'eyeColor' => 'Verdi',
            'bodyType' => 'Media',
            'noseDetail' => null,
            'mouthDetail' => null,
            'distinguishingDetail' => 'Porta sempre occhiali tondi',
        ])->assertSet('step', 'summary')->assertHasNoErrors();

        $draft = CharacterDraft::first();
        $this->assertNotNull($draft);
        $this->assertSame('Intrattenere', $draft->goal);
        $this->assertSame(['Tecnologia', 'Cucina'], $draft->niche);
        $this->assertSame('Test Character', $draft->name);
        $this->assertSame('Vive di caffè e debug notturni', $draft->one_liner);
        $this->assertSame(['curioso', 'pragmatico', 'calmo', 'generoso'], $draft->traits);
        $this->assertSame(30, $draft->communication_formality);
        $this->assertSame('leggero', $draft->humor_level);
        $this->assertSame(['Lavoro', 'Tecnologia', 'Traffico', 'Cibo', 'Burocrazia'], $draft->joke_targets);
        $this->assertSame('Castano', $draft->hair_color);
        $this->assertSame('Medi', $draft->hair_length);
        $this->assertSame('Ricci', $draft->hair_style);
        $this->assertSame('in_corso', $draft->status);
    }

    public function test_appearance_step_rejects_a_texture_without_a_hair_length(): void
    {
        // hairStyle ora è solo texture/acconciatura (facoltativa): scegliere una texture senza
        // indicare hairLength deve restare bloccato, altrimenti si ripete il bug reale trovato
        // su Sofia (profilo visivo muto sulla lunghezza dei capelli).
        Livewire::test(CharacterCreationWizard::class)
            ->call('$set', 'step', 'appearance')
            ->call('nextStep', 'appearance', 'summary', [
                'ageRange' => '26-35',
                'presentation' => 'Femminile',
                'styleArchetype' => 'Boho',
                'hairStyle' => 'Ricci',
            ])
            ->assertHasErrors(['hairLength'])
            ->assertSet('step', 'appearance');
    }

    public function test_why_step_rejects_fewer_than_two_niches(): void
    {
        Livewire::test(CharacterCreationWizard::class)
            ->call('$set', 'step', 'why')
            ->call('nextStep', 'why', 'identity', [
                'goal' => 'Intrattenere',
                'niche' => ['Tecnologia'],
            ])
            ->assertHasErrors(['niche'])
            ->assertSet('step', 'why');

        $this->assertNull(CharacterDraft::first());
    }

    public function test_why_step_requires_a_goal(): void
    {
        Livewire::test(CharacterCreationWizard::class)
            ->call('$set', 'step', 'why')
            ->call('nextStep', 'why', 'identity', [
                'goal' => null,
                'niche' => ['Tecnologia', 'Cucina'],
            ])
            ->assertHasErrors(['goal'])
            ->assertSet('step', 'why');
    }

    public function test_humor_step_requires_five_joke_targets_unless_never(): void
    {
        Livewire::test(CharacterCreationWizard::class)
            ->call('$set', 'step', 'humor')
            ->call('nextStep', 'humor', 'appearance', [
                'humorLevel' => 'leggero',
                'jokeTargets' => ['Lavoro', 'Tecnologia'],
            ])
            ->assertHasErrors(['jokeTargets'])
            ->assertSet('step', 'humor');
    }

    public function test_humor_step_allows_mai_without_joke_targets(): void
    {
        Livewire::test(CharacterCreationWizard::class)
            ->call('nextStep', 'humor', 'appearance', [
                'humorLevel' => 'mai',
                'jokeTargets' => [],
            ])
            ->assertHasNoErrors()
            ->assertSet('step', 'appearance');

        $draft = CharacterDraft::first();
        $this->assertSame('mai', $draft->humor_level);
        $this->assertSame([], $draft->joke_targets);
    }

    public function test_humor_step_restores_default_safe_topics_even_if_client_removed_them(): void
    {
        Livewire::test(CharacterCreationWizard::class)
            ->call('nextStep', 'humor', 'appearance', [
                'humorLevel' => 'forte',
                'jokeTargets' => ['Lavoro', 'Tecnologia', 'Traffico', 'Cibo', 'Burocrazia'],
                'humorSafeTopics' => [], // client "malevolo"/bug: prova a svuotare i default di sicurezza
            ])
            ->assertHasNoErrors();

        $draft = CharacterDraft::first();
        $topics = $draft->humor_safe_topics;
        sort($topics);
        $expected = ['Aspetto fisico', 'Difficoltà economiche', 'Lutti e tragedie', 'Salute / malattie', 'Salute mentale'];
        sort($expected);
        $this->assertSame($expected, $topics);
    }

    public function test_save_and_continue_redirects_to_register_when_not_authenticated(): void
    {
        Livewire::test(CharacterCreationWizard::class)
            ->call('nextStep', 'why', 'identity', ['goal' => 'Educare', 'niche' => ['Fitness', 'Salute']])
            ->call('nextStep', 'identity', 'personality', ['name' => 'Prova', 'oneLiner' => 'Un personaggio di prova'])
            ->call('saveAndContinue')
            ->assertRedirect(route('register'));

        // La bozza resta persistita (non si perde nulla nel passaggio) ma non è ancora
        // convertita: niente personaggi "fluttuanti" associati a nessuno finché non c'è un account.
        $draft = CharacterDraft::first();
        $this->assertSame('Educare', $draft->goal);
        $this->assertSame('in_corso', $draft->status);
        $this->assertNull($draft->tenant_id);
        $this->assertTrue(session('save_requires_auth'));
    }

    public function test_save_and_continue_requires_identity_fields_even_if_client_skips_the_step(): void
    {
        Livewire::test(CharacterCreationWizard::class)
            ->call('nextStep', 'why', 'identity', ['goal' => 'Educare', 'niche' => ['Fitness', 'Salute']])
            ->call('saveAndContinue') // salta lo step "identity": name/oneLiner restano null
            ->assertHasErrors(['name', 'oneLiner']);

        $this->assertNull(CharacterDraft::first()->tenant_id ?? null);
    }

    public function test_save_and_continue_converts_immediately_when_already_authenticated(): void
    {
        $tenant = \App\Models\Tenant::create(['name' => 'Già Loggato', 'email' => 'gia-loggato@example.com', 'status' => 'trial']);
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        Livewire::actingAs($user)
            ->test(CharacterCreationWizard::class)
            ->call('nextStep', 'why', 'identity', ['goal' => 'Educare', 'niche' => ['Fitness', 'Salute']])
            ->call('nextStep', 'identity', 'personality', ['name' => 'Prova', 'oneLiner' => 'Un personaggio di prova'])
            ->call('saveAndContinue')
            ->assertRedirect(route('character.panel'));

        $draft = CharacterDraft::first();
        $this->assertSame('convertito', $draft->status);
        $this->assertSame($tenant->id, $draft->tenant_id);
        $this->assertSame(1, $tenant->characters()->count());
    }

    public function test_resuming_with_same_session_hydrates_existing_draft(): void
    {
        $first = Livewire::test(CharacterCreationWizard::class);
        $first->call('nextStep', 'why', 'identity', ['goal' => 'Vendere prodotti o servizi', 'niche' => ['Moda', 'Sport']]);

        $this->assertSame(1, CharacterDraft::count());

        // Stesso processo PHP -> stessa sessione Laravel -> deve ritrovare la bozza già creata
        // (session_token persistito in sessione), non crearne una seconda.
        $second = Livewire::test(CharacterCreationWizard::class);
        $second->assertSet('goal', 'Vendere prodotti o servizi')
            ->assertSet('niche', ['Moda', 'Sport'])
            ->assertSet('step', 'intro');

        $this->assertSame(1, CharacterDraft::count());
    }
}
