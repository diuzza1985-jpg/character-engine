<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\ConvertCharacterDraftJob;
use App\Models\CharacterDraft;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Registrazione minima scritta a mano (nessun Fortify/Breeze/Jetstream): il progetto non aveva
 * finora alcun flusso di registrazione pubblico, solo CRUD admin via Filament per Tenant/User.
 * Stesso principio "niente SDK di terze parti" già seguito altrove nel codebase.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'plan_id' => null,
            'status' => 'trial',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // cast 'hashed' sul modello, nessun Hash::make manuale necessario
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $draft = CharacterDraft::where('session_token', session('character_draft_token'))
            ->where('status', '!=', 'convertito')
            ->latest('created_at')
            ->first();

        if ($draft) {
            // dispatchSync, non dispatch(): nessuna chiamata esterna qui dentro (solo scritture
            // DB locali), quindi non serve un vero worker di coda per avere il personaggio
            // pronto subito al primo caricamento del pannello post-registrazione.
            ConvertCharacterDraftJob::dispatchSync($draft->id, $tenant->id);
            session()->forget('save_requires_auth');
        }

        return redirect()->route('character.panel');
    }
}
