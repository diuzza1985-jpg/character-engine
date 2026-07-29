<?php

namespace App\Livewire;

use App\Models\Character;
use Livewire\Component;

/**
 * Cestino personaggi (/personaggi/cestino): destinazione dei personaggi eliminati dal menu
 * (eliminazione logica, SoftDeletes) — ripristinabili, o eliminabili per sempre da qui
 * (forceDelete: quella sì fisica e irreversibile, dietro un secondo avviso più severo del primo).
 */
class CharacterTrash extends Component
{
    public function restore(int $characterId): void
    {
        $tenant = auth()->user()->tenant;
        abort_unless($tenant, 403);

        $character = Character::onlyTrashed()->where('tenant_id', $tenant->id)->findOrFail($characterId);
        $character->restore();
    }

    /**
     * Fisica, non logica: cancella davvero la riga. Le tabelle collegate (bible, profilo
     * visivo, asset, post, generazioni...) hanno tutte cascadeOnDelete sulla FK character_id,
     * quindi il DB si occupa da solo di ripulire tutto il resto — nessun cleanup manuale qui.
     */
    public function forceDelete(int $characterId): void
    {
        $tenant = auth()->user()->tenant;
        abort_unless($tenant, 403);

        $character = Character::onlyTrashed()->where('tenant_id', $tenant->id)->findOrFail($characterId);
        $character->forceDelete();
    }

    public function render()
    {
        $tenant = auth()->user()->tenant;

        $characters = $tenant
            ? Character::onlyTrashed()->where('tenant_id', $tenant->id)->latest('deleted_at')->get()
            : collect();

        return view('livewire.character-trash', ['characters' => $characters])->extends('layouts.dashboard');
    }
}
