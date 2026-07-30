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

        // 'name' è l'unica colonna NOT NULL su characters oltre a tenant_id/slug/status (verificato
        // nella migration): una bozza che non l'ha ancora (sessione abbandonata prima dello step
        // "identity", es. un visitatore che poi si limita a fare login su un account già esistente
        // non collegato a quella bozza) non è una bozza da completare, è solo un artefatto di
        // sessione — provare comunque a convertirla fa fallire uniqueSlug(null) più a valle.
        if ($draft && filled($draft->name)) {
            $tenant = $request->user()->resolveOrCreateTenant();
            ConvertCharacterDraftJob::dispatchSync($draft->id, $tenant->id);
            session()->forget('save_requires_auth');
        }

        return redirect()->route('character.panel');
    }
}
