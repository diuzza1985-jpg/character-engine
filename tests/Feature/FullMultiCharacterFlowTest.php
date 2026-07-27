<?php

namespace Tests\Feature;

use App\Console\Commands\MaterializeWeeklySchedule;
use App\Livewire\CharacterCreationWizard;
use App\Livewire\CharacterDetail;
use App\Models\Character;
use App\Models\CharacterKinship;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\WeeklyScheduleSlot;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Scenario end-to-end descritto nel piano "menu multi-personaggio": bozza anonima ->
 * registrazione -> personaggio "salvato" -> collega Instagram -> attiva -> verifica scheduling;
 * secondo personaggio (Fernando) creato da loggato, collegato per parentela, resta "salvato" e
 * non genera mai scheduled_posts; riapertura del questionario di Sofia, modifica, salvataggio
 * update-in-place.
 */
class FullMultiCharacterFlowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_full_scenario(): void
    {
        // 1. Bozza anonima per "Sofia".
        $wizard = Livewire::test(CharacterCreationWizard::class)
            ->call('nextStep', 'why', 'identity', ['goal' => 'Intrattenere', 'niche' => ['Tecnologia', 'Cucina']])
            ->call('nextStep', 'identity', 'personality', ['name' => 'Sofia E2E', 'oneLiner' => 'Sviluppatrice appassionata di cucina'])
            ->call('nextStep', 'personality', 'voice', ['traits' => ['curioso', 'calmo', 'generoso', 'pragmatico'], 'coreValues' => ['Libertà', 'Creatività'], 'dislikes' => ['Ritardi', 'Rumore']])
            ->call('nextStep', 'voice', 'humor', ['communicationFormality' => 40, 'communicationVerbosity' => 60, 'communicationDirectness' => 50, 'emojiUsage' => 'raramente'])
            ->call('nextStep', 'humor', 'appearance', ['humorLevel' => 'leggero', 'jokeTargets' => ['Lavoro', 'Tecnologia', 'Traffico', 'Cibo', 'Burocrazia']])
            ->call('nextStep', 'appearance', 'summary', ['ageRange' => '26-35', 'presentation' => 'Femminile', 'styleArchetype' => 'Casual sportivo'])
            ->call('saveAndContinue');

        $wizard->assertRedirect(route('register'));

        // 2. Registrazione -> conversione automatica in Character "salvato".
        $response = $this->post(route('register.store'), [
            'name' => 'Utente E2E',
            'email' => 'e2e-flow@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $response->assertRedirect(route('character.panel'));

        $user = \App\Models\User::where('email', 'e2e-flow@example.com')->first();
        $tenant = $user->tenant;
        $sofia = $tenant->characters()->where('name', 'Sofia E2E')->first();
        $this->assertNotNull($sofia);
        $this->assertSame('draft', $sofia->status, 'appena convertito, un personaggio è "salvato" (draft), non attivo');

        // 3. Senza Instagram collegato, l'attivazione è bloccata.
        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $sofia])
            ->call('toggleActive')
            ->assertHasErrors('activation');
        $this->assertSame('draft', $sofia->fresh()->status);

        // 4. Collega Instagram (simulato, nessuna vera chiamata OAuth) -> ora l'attivazione funziona.
        SocialAccount::create(['character_id' => $sofia->id, 'platform' => 'instagram', 'ig_user_id' => 'ig-e2e', 'status' => 'connected']);
        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $sofia])
            ->call('toggleActive')
            ->assertHasNoErrors();
        $sofia->refresh();
        $this->assertSame('active', $sofia->status);

        // 5. Con uno slot ricorrente abilitato, MaterializeWeeklySchedule ora la considera (era il
        //    gap preesistente chiuso da questo piano).
        WeeklyScheduleSlot::create(['character_id' => $sofia->id, 'day_of_week' => now()->dayOfWeek, 'time_of_day' => '09:00', 'post_type' => 'image', 'mode' => 'auto', 'enabled' => true]);
        $this->artisan('schedule:materialize-weekly')->assertSuccessful();
        $this->assertGreaterThan(0, ScheduledPost::where('character_id', $sofia->id)->count());

        // 6. Secondo personaggio (Fernando) creato da loggato, tramite lo stesso wizard.
        Livewire::actingAs($user)->test(CharacterCreationWizard::class)
            ->call('nextStep', 'why', 'identity', ['goal' => 'Fare storytelling', 'niche' => ['Casa e famiglia', 'Sport']])
            ->call('nextStep', 'identity', 'personality', ['name' => 'Fernando E2E', 'oneLiner' => 'Sempre a un progetto dal disastro'])
            ->call('nextStep', 'personality', 'voice', ['traits' => ['spontaneo', 'ottimista', 'generoso', 'curioso'], 'coreValues' => ['Famiglia', 'Avventura'], 'dislikes' => ['Disordine', 'Noia']])
            ->call('nextStep', 'voice', 'humor', ['communicationFormality' => 30, 'communicationVerbosity' => 70, 'communicationDirectness' => 60, 'emojiUsage' => 'spesso'])
            ->call('nextStep', 'humor', 'appearance', ['humorLevel' => 'forte', 'jokeTargets' => ['Famiglia', 'Traffico', 'Cibo', 'Burocrazia', 'Diete fallite']])
            ->call('nextStep', 'appearance', 'summary', ['ageRange' => '36-50', 'presentation' => 'Maschile', 'styleArchetype' => 'Streetwear'])
            ->call('saveAndContinue')
            ->assertRedirect(route('character.panel'));

        $fernando = $tenant->fresh()->characters()->where('name', 'Fernando E2E')->first();
        $this->assertNotNull($fernando);
        $this->assertSame('draft', $fernando->status);
        $this->assertSame(2, $tenant->fresh()->characters()->count());

        // 7. Collega Fernando a Sofia per parentela.
        Livewire::actingAs($user)->test(CharacterDetail::class, ['character' => $sofia])
            ->set('newRelatedCharacterId', (string) $fernando->id)
            ->set('newRelationshipType', 'marito')
            ->call('addKinship')
            ->assertHasNoErrors();
        $this->assertSame(1, CharacterKinship::where('character_id', $sofia->id)->where('related_character_id', $fernando->id)->count());

        // 8. Fernando NON ha Instagram collegato: resta "salvato" per sempre, anche con uno slot.
        WeeklyScheduleSlot::create(['character_id' => $fernando->id, 'day_of_week' => now()->dayOfWeek, 'time_of_day' => '10:00', 'post_type' => 'image', 'mode' => 'auto', 'enabled' => true]);
        $this->artisan('schedule:materialize-weekly')->assertSuccessful();
        $this->assertSame(0, ScheduledPost::where('character_id', $fernando->id)->count(), 'Fernando non deve mai generare scheduled_posts, non è attivo');

        // 9. Riapertura del questionario di Sofia (modalità modifica), aggiornamento in-place.
        Livewire::actingAs($user)->test(CharacterCreationWizard::class, ['character' => $sofia])
            ->assertSet('name', 'Sofia E2E')
            ->assertSet('goal', 'Intrattenere')
            ->call('nextStep', 'why', 'identity', ['goal' => 'Educare', 'niche' => ['Tecnologia', 'Cucina', 'Salute']])
            ->set('oneLiner', 'Sviluppatrice e content creator di cucina sana')
            ->call('saveAndContinue')
            ->assertRedirect(route('character.panel'));

        $sofia->refresh();
        $this->assertSame('Sviluppatrice e content creator di cucina sana', $sofia->one_liner);
        $this->assertSame(2, Character::where('tenant_id', $tenant->id)->count(), 'nessun personaggio duplicato dopo la modifica');

        $editorialSettings = \App\Models\CharacterEditorialSettings::where('character_id', $sofia->id)->first();
        $this->assertSame('Educare', $editorialSettings->goal);
    }
}
