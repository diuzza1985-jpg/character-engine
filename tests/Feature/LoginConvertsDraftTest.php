<?php

namespace Tests\Feature;

use App\Livewire\CharacterCreationWizard;
use App\Models\CharacterDraft;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class LoginConvertsDraftTest extends TestCase
{
    use DatabaseTransactions;

    public function test_logging_in_completes_a_pending_draft_from_the_same_session(): void
    {
        $tenant = Tenant::create(['name' => 'Utente Esistente', 'email' => 'esistente@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner', 'password' => 'password123']);

        // L'utente compila il wizard da anonimo, clicca "Salva e continua" senza essere loggato...
        Livewire::test(CharacterCreationWizard::class)
            ->call('nextStep', 'why', 'identity', ['goal' => 'Divulgare', 'niche' => ['Diritto', 'Finanza']])
            ->call('nextStep', 'identity', 'personality', ['name' => 'Prova Login', 'oneLiner' => 'Un personaggio di prova'])
            ->call('saveAndContinue')
            ->assertRedirect(route('register'));

        $draft = CharacterDraft::first();
        $this->assertSame('in_corso', $draft->status);
        $this->assertTrue(session('save_requires_auth'));

        // ...ma ha già un account: invece di registrarsi, accede (stessa sessione = stesso
        // session_token della bozza appena creata).
        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('character.panel'));
        $this->assertAuthenticatedAs($user);

        $draft->refresh();
        $this->assertSame('convertito', $draft->status);
        $this->assertSame($tenant->id, $draft->tenant_id);
        $this->assertSame(1, $tenant->characters()->count());
        $this->assertFalse(session()->has('save_requires_auth'));
    }

    public function test_logging_in_with_no_existing_tenant_auto_provisions_one(): void
    {
        // Riproduce il caso reale: un utente pre-esistente (creato prima che esistesse la
        // registrazione pubblica, es. via seeder) con tenant_id null.
        $user = User::factory()->create(['tenant_id' => null, 'role' => 'owner', 'password' => 'password123']);
        $this->assertNull($user->tenant_id);

        Livewire::test(CharacterCreationWizard::class)
            ->call('nextStep', 'why', 'identity', ['goal' => 'Educare', 'niche' => ['Salute', 'Sport']])
            ->call('nextStep', 'identity', 'personality', ['name' => 'Paolo', 'oneLiner' => 'Un personaggio di prova'])
            ->call('saveAndContinue')
            ->assertRedirect(route('register'));

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('character.panel'));

        $user->refresh();
        $this->assertNotNull($user->tenant_id, 'doveva auto-provisionare un tenant invece di andare in errore');

        $draft = CharacterDraft::first();
        $this->assertSame('convertito', $draft->status);
        $this->assertSame($user->tenant_id, $draft->tenant_id);
        $this->assertSame(1, $user->tenant->characters()->count());
        $this->assertSame('Paolo', $user->tenant->characters()->first()->name);
    }

    public function test_logging_in_reuses_an_existing_tenant_with_the_same_email_instead_of_duplicating(): void
    {
        // Riproduce il bug reale trovato in produzione: un Tenant con la stessa email esiste
        // già (creato per un'altra via, es. admin Filament) ma non è mai stato collegato
        // all'utente — un Tenant::create() diretto violerebbe il vincolo unique su tenants.email.
        $existingTenant = Tenant::create(['name' => 'Già Esistente', 'email' => 'stessaemaildeltenant@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => null, 'email' => 'stessaemaildeltenant@example.com', 'password' => 'password123']);

        Livewire::test(CharacterCreationWizard::class)
            ->call('nextStep', 'why', 'identity', ['goal' => 'Educare', 'niche' => ['Salute', 'Sport']])
            ->call('nextStep', 'identity', 'personality', ['name' => 'Duplicato Test', 'oneLiner' => 'Un personaggio di prova'])
            ->call('saveAndContinue')
            ->assertRedirect(route('register'));

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('character.panel'));

        $user->refresh();
        $this->assertSame($existingTenant->id, $user->tenant_id, 'doveva riusare il tenant esistente, non crearne uno nuovo');
        $this->assertSame(1, Tenant::where('email', 'stessaemaildeltenant@example.com')->count(), 'non deve esistere un secondo tenant con la stessa email');
        $this->assertSame(1, $existingTenant->characters()->count());
    }

    public function test_login_rejects_wrong_credentials(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'sbagliata',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
