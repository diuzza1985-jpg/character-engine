<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\ConvertCharacterDraftJob;
use App\Models\CharacterDraft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login minimo scritto a mano, stesso principio di RegisterController — necessario perché il
 * middleware "auth" su /il-mio-personaggio deve poter reindirizzare da qualche parte per gli
 * utenti non autenticati (altrimenti "Route [login] not defined"), e perché un utente già
 * registrato che arriva da "Salva e continua" deve poter accedere (non solo registrarsi di
 * nuovo) per completare il salvataggio della bozza in corso.
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'Credenziali non valide.']);
        }

        $request->session()->regenerate();

        $draft = CharacterDraft::where('session_token', session('character_draft_token'))
            ->where('status', '!=', 'convertito')
            ->latest('created_at')
            ->first();

        if ($draft) {
            $tenant = $request->user()->resolveOrCreateTenant();
            ConvertCharacterDraftJob::dispatchSync($draft->id, $tenant->id);
            session()->forget('save_requires_auth');
        }

        return redirect()->route('character.panel');
    }
}
