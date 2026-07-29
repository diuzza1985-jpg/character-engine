<?php

namespace App\Livewire;

use App\Models\Character;
use App\Models\CharacterDraft;
use Livewire\Component;

/**
 * Menu personaggi (/personaggi): sostituisce il vecchio pannello a tab singolo (un tenant può
 * avere più personaggi, es. Sofia + Fernando collegati per parentela). Mostra sia le bozze in
 * corso (mai convertite) sia i personaggi reali, con lo stato bozza/salvato/attivo.
 */
class CharacterList extends Component
{
    /**
     * Eliminazione logica (SoftDeletes, già sul modello Character): il personaggio sparisce da
     * qui e da ogni query dell'app (scope globale di Eloquent) ma resta nel DB, recuperabile dal
     * cestino (/personaggi/cestino) — mai una delete() fisica da questo menu.
     */
    public function deleteCharacter(int $characterId): void
    {
        $tenant = auth()->user()->tenant;
        abort_unless($tenant, 403);

        $character = Character::where('tenant_id', $tenant->id)->findOrFail($characterId);
        $character->delete();
    }

    /**
     * Fisica, non logica: le bozze non hanno SoftDeletes (stesso trattamento già riservato loro
     * da character-drafts:prune-expired, che le elimina fisicamente alla scadenza) — non portano
     * identità/asset/bible di un personaggio reale, non serve un cestino per recuperarle.
     */
    public function deleteDraft(string $draftId): void
    {
        $tenant = auth()->user()->tenant;
        abort_unless($tenant, 403);

        $draft = CharacterDraft::where('tenant_id', $tenant->id)->whereNull('character_id')->findOrFail($draftId);
        $draft->delete();
    }

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

        $trashedCount = $tenant
            ? Character::onlyTrashed()->where('tenant_id', $tenant->id)->count()
            : 0;

        return view('livewire.character-list', [
            'drafts' => $drafts,
            'characters' => $characters,
            'trashedCount' => $trashedCount,
        ])->extends('layouts.dashboard');
    }
}
