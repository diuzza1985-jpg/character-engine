<?php

namespace App\Livewire;

use App\Models\CharacterDraft;
use Livewire\Component;

/**
 * Menu personaggi (/personaggi): sostituisce il vecchio pannello a tab singolo (un tenant può
 * avere più personaggi, es. Sofia + Fernando collegati per parentela). Mostra sia le bozze in
 * corso (mai convertite) sia i personaggi reali, con lo stato bozza/salvato/attivo.
 */
class CharacterList extends Component
{
    public function render()
    {
        $tenant = auth()->user()->tenant;

        $drafts = $tenant
            ? CharacterDraft::where('tenant_id', $tenant->id)
                ->whereNull('character_id')
                ->where('status', '!=', 'convertito')
                ->latest('updated_at')
                ->get()
            : collect();

        $characters = $tenant
            ? $tenant->characters()->with('socialAccount')->latest('id')->get()
            : collect();

        return view('livewire.character-list', [
            'drafts' => $drafts,
            'characters' => $characters,
        ])->extends('layouts.public');
    }
}
