<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class CharacterPanelController extends Controller
{
    public function show(Request $request): View
    {
        $tenant = $request->user()->tenant;
        $character = $tenant?->characters()->latest('id')->first();

        return view('character-panel', [
            'character' => $character,
            'creditBalance' => $tenant?->creditBalance() ?? 0,
        ]);
    }
}
