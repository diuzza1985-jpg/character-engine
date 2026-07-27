<?php

namespace App\Livewire;

use App\Models\Character;
use App\Models\CharacterKinship;
use Livewire\Component;

/**
 * Dettaglio personaggio (/personaggi/{character}), sostituisce il vecchio pannello: riepilogo
 * di SOLA LETTURA delle risposte del questionario (mai il prompt/la prosa bible generata — quella
 * resta un dettaglio implementativo, l'utente non deve vederla né tanto meno toccarla), parentele
 * con altri personaggi dello stesso tenant, attivazione/pausa (gated su Instagram collegato), gate
 * crediti riusato dalla consegna precedente.
 */
class CharacterDetail extends Component
{
    public Character $character;
    public string $newRelatedCharacterId = '';
    public string $newRelationshipType = '';
    public string $newRelationshipNotes = '';

    public function mount(Character $character): void
    {
        abort_unless($character->tenant_id === auth()->user()->tenant_id, 403);
        $this->character = $character;
    }

    public function addKinship(): void
    {
        $this->validate([
            'newRelatedCharacterId' => ['required', 'integer', 'different:character.id'],
            'newRelationshipType' => ['required', 'string', 'in:' . implode(',', CharacterKinship::RELATIONSHIP_TYPES)],
        ], [], ['newRelatedCharacterId' => 'personaggio', 'newRelationshipType' => 'tipo di relazione']);

        $related = Character::where('tenant_id', $this->character->tenant_id)
            ->where('id', $this->newRelatedCharacterId)
            ->firstOrFail();

        CharacterKinship::updateOrCreate(
            ['character_id' => $this->character->id, 'related_character_id' => $related->id],
            ['relationship_type' => $this->newRelationshipType, 'notes' => $this->newRelationshipNotes ?: null]
        );

        $this->reset(['newRelatedCharacterId', 'newRelationshipType', 'newRelationshipNotes']);
    }

    public function removeKinship(int $kinshipId): void
    {
        CharacterKinship::where('character_id', $this->character->id)->where('id', $kinshipId)->delete();
    }

    /**
     * "Attivo" pubblica davvero (motore editoriale + scheduling lo processano) — possibile solo
     * con un account Instagram collegato, altrimenti resta "salvato" (esiste, personalità
     * propria, ma non pubblica mai — es. Fernando, appoggiato solo alle storyline di Sofia).
     */
    public function toggleActive(): void
    {
        if ($this->character->status === 'active') {
            $this->character->update(['status' => 'draft']);

            return;
        }

        if (! $this->character->socialAccount || $this->character->socialAccount->status !== 'connected') {
            $this->addError('activation', 'Collega prima un account Instagram per poter attivare questo personaggio.');

            return;
        }

        $this->character->update(['status' => 'active']);
    }

    public function render()
    {
        $tenant = auth()->user()->tenant;

        $otherCharacters = $tenant
            ? $tenant->characters()->where('id', '!=', $this->character->id)->get()
            : collect();

        $kinships = $this->character->kinships()->with('relatedCharacter')->get();
        $kinshipsAsRelated = $this->character->kinshipsAsRelated()->with('character')->get();

        return view('livewire.character-detail', [
            'draft' => $this->character->draft,
            'otherCharacters' => $otherCharacters,
            'kinships' => $kinships,
            'kinshipsAsRelated' => $kinshipsAsRelated,
            'creditBalance' => $tenant?->creditBalance() ?? 0,
            'relationshipTypes' => CharacterKinship::RELATIONSHIP_TYPES,
        ])->extends('layouts.dashboard');
    }
}
