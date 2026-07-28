<?php

namespace Tests\Feature;

use App\Livewire\CharacterCreationWizard;
use App\Models\CharacterBibleSection;
use App\Models\CharacterDraft;
use App\Models\CharacterEditorialSettings;
use App\Models\CharacterVisualProfile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationConvertsDraftTest extends TestCase
{
    use DatabaseTransactions;

    private function fillDraftThroughSummary(): void
    {
        Livewire::test(CharacterCreationWizard::class)
            ->call('nextStep', 'why', 'identity', ['goal' => 'Intrattenere', 'niche' => ['Tecnologia', 'Cucina']])
            ->call('nextStep', 'identity', 'personality', ['name' => 'Prova', 'oneLiner' => 'Un personaggio di prova', 'livingSituation' => 'Da solo/a', 'environment' => 'Città'])
            ->call('nextStep', 'personality', 'voice', ['traits' => ['curioso', 'calmo', 'generoso', 'pragmatico'], 'coreValues' => ['Libertà', 'Creatività'], 'dislikes' => ['Ritardi', 'Rumore']])
            ->call('nextStep', 'voice', 'humor', ['communicationFormality' => 20, 'communicationVerbosity' => 80, 'communicationDirectness' => 90, 'emojiUsage' => 'spesso'])
            ->call('nextStep', 'humor', 'appearance', ['humorLevel' => 'forte', 'jokeTargets' => ['Lavoro', 'Tecnologia', 'Traffico', 'Cibo', 'Burocrazia']])
            ->call('nextStep', 'appearance', 'summary', ['ageRange' => '26-35', 'presentation' => 'Femminile', 'styleArchetype' => 'Boho', 'hairColor' => 'Rosso', 'hairLength' => 'Lunghi', 'distinguishingDetail' => 'Un tatuaggio sul polso']);
    }

    public function test_registration_converts_the_session_draft_into_a_full_character(): void
    {
        $this->fillDraftThroughSummary();
        $this->assertSame(1, CharacterDraft::count());
        $draft = CharacterDraft::first();

        $response = $this->post(route('register.store'), [
            'name' => 'Mario Rossi',
            'email' => 'mario.test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('character.panel'));

        $user = User::where('email', 'mario.test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertAuthenticatedAs($user);
        $this->assertSame('owner', $user->role);

        $tenant = Tenant::find($user->tenant_id);
        $this->assertNotNull($tenant);
        $this->assertSame('Mario Rossi', $tenant->name);
        $this->assertNull($tenant->plan_id);
        $this->assertSame('trial', $tenant->status);

        $draft->refresh();
        $this->assertSame('convertito', $draft->status);
        $this->assertSame($tenant->id, $draft->tenant_id);

        $character = $tenant->characters()->first();
        $this->assertNotNull($character);
        $this->assertSame('Prova', $character->name);
        $this->assertSame('Un personaggio di prova', $character->one_liner);
        $this->assertNotNull($character->slug);

        $settings = CharacterEditorialSettings::where('character_id', $character->id)->first();
        $this->assertSame('Intrattenere', $settings->goal);
        $this->assertSame(['Tecnologia', 'Cucina'], $settings->niche);

        $visualProfile = CharacterVisualProfile::where('character_id', $character->id)->first();
        $this->assertNotNull($visualProfile);
        $this->assertStringContainsString('26-35', $visualProfile->age_description);

        $sections = CharacterBibleSection::where('character_id', $character->id)->pluck('content', 'section_key');
        foreach (['valori', 'voce', 'umorismo', 'famiglia', 'regole'] as $key) {
            $this->assertArrayHasKey($key, $sections, "Sezione bible mancante: {$key}");
            $this->assertNotEmpty(trim($sections[$key]));
        }
        $this->assertStringContainsString('Lavoro', $sections['umorismo']);
        // communicationFormality=20 (vicino a "Formale" nello slider, valore basso) -> "molto formale".
        $this->assertStringContainsString('molto formale', $sections['voce']);
    }

    public function test_registration_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'duplicato@example.com']);

        $response = $this->post(route('register.store'), [
            'name' => 'Altro',
            'email' => 'duplicato@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_registration_without_a_prior_draft_still_creates_the_account(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Senza Bozza',
            'email' => 'senzabozza@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('character.panel'));
        $this->assertNotNull(User::where('email', 'senzabozza@example.com')->first());
    }
}
