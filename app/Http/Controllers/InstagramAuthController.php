<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\SocialAccount;
use App\Services\InstagramGraphService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InstagramAuthController extends Controller
{
    public function __construct(private InstagramGraphService $instagram) {}

    public function redirect(Request $request, Character $character)
    {
        $state = Str::random(40);

        $request->session()->put('instagram_oauth_state', $state);
        $request->session()->put('instagram_oauth_character_id', $character->id);

        return redirect($this->instagram->buildAuthorizationUrl($state));
    }

    public function callback(Request $request)
    {
        $expectedState = $request->session()->pull('instagram_oauth_state');
        $characterId = $request->session()->pull('instagram_oauth_character_id');

        if ($request->query('error')) {
            return redirect('/aiadmin')->with('error', 'Autorizzazione Instagram negata: ' . $request->query('error_description', $request->query('error')));
        }

        if (! $characterId || $request->query('state') !== $expectedState) {
            abort(419, 'Sessione OAuth non valida o scaduta, riprova il collegamento.');
        }

        $character = Character::findOrFail($characterId);

        // Instagram a volte aggiunge un suffisso #_ al redirect, va ripulito
        $code = rtrim((string) $request->query('code'), '#_');

        $shortLived = $this->instagram->exchangeCodeForToken($code);
        $longLived = $this->instagram->exchangeForLongLivedToken($shortLived['access_token']);
        $profile = $this->instagram->getProfile($longLived['access_token']);

        SocialAccount::updateOrCreate(
            [
                'character_id' => $character->id,
                'platform' => 'instagram',
            ],
            [
                'ig_user_id' => $shortLived['user_id'],
                'access_token' => $longLived['access_token'],
                'token_expires_at' => now()->addSeconds($longLived['expires_in']),
                'status' => 'connected',
                'last_refreshed_at' => now(),
            ]
        );

        return redirect("/aiadmin/characters/{$character->id}/edit")
            ->with('success', "Instagram collegato: @{$profile['username']}");
    }
}
