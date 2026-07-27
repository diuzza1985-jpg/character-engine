<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Login minimo scritto a mano, stesso principio di RegisterController — necessario perché il
 * middleware "auth" su /il-mio-personaggio deve poter reindirizzare da qualche parte per gli
 * utenti non autenticati (altrimenti "Route [login] not defined").
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

        return redirect()->route('character.panel');
    }
}
