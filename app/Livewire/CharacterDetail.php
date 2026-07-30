<?php

namespace App\Livewire;

use App\Models\AssetUploadConsent;
use App\Models\Character;
use App\Models\CharacterAsset;
use App\Models\CharacterKinship;
use App\Services\HouseholdAnimalSync;
use App\Support\AssetUploadConsentText;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Dettaglio personaggio (/personaggi/{character}), sostituisce il vecchio pannello: riepilogo
 * di SOLA LETTURA delle risposte del questionario (mai il prompt/la prosa bible generata — quella
 * resta un dettaglio implementativo, l'utente non deve vederla né tanto meno toccarla), parentele
 * con altri personaggi dello stesso tenant, attivazione/pausa (gated su Instagram collegato), gate
 * crediti riusato dalla consegna precedente, upload asset dell'utente (Fase 4 mondo narrativo).
 *
 * Funziona per QUALUNQUE Character del tenant, non solo per quello principale: aprire la propria
 * pagina di un personaggio "del mondo" (es. Fernando) e caricargli qui una foto è esattamente
 * come si chiude il bug noto di Pippo/Fernando senza reference visiva (nessun cambiamento al
 * wizard necessario, la funzione è già generica per Character).
 */
class CharacterDetail extends Component
{
    use WithFileUploads;

    public Character $character;
    public string $newRelatedCharacterId = '';
    public string $newRelationshipType = '';
    public string $newRelationshipNotes = '';
    public string $newAssetType = '';
    public $newAssetFile = null;
    public bool $newAssetConsent = false;

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

        // Se questa parentela è genitore/figlio (l'unica considerata "stesso nucleo familiare",
        // vedi HouseholdAnimalSync::householdMembersOf), un animale già dichiarato da uno dei due
        // deve raggiungere subito anche l'altro — non solo alla prossima riapertura del
        // questionario (limite reale segnalato dall'utente, prima di questo aggancio).
        app(HouseholdAnimalSync::class)->syncAllForCharacter($this->character);

        $this->reset(['newRelatedCharacterId', 'newRelationshipType', 'newRelationshipNotes']);
    }

    public function removeKinship(int $kinshipId): void
    {
        CharacterKinship::where('character_id', $this->character->id)->where('id', $kinshipId)->delete();
    }

    /**
     * Fase 4 mondo narrativo: upload diretto (mai FalImageService, nessuna chiamata Fal.ai) —
     * il consenso è una checkbox separata dai Termini di Servizio generali, obbligatoria (regola
     * 'accepted'), e produce un evento auditabile (AssetUploadConsent: chi, quando, quale
     * versione esatta del testo) invece di restare solo una spunta lato interfaccia.
     */
    public function uploadAsset(): void
    {
        $this->validate([
            'newAssetType' => ['required', 'string', 'max:255'],
            'newAssetFile' => ['required', 'image', 'max:10240'],
            'newAssetConsent' => ['accepted'],
        ], [
            'newAssetConsent.accepted' => 'Devi confermare la dichiarazione sui diritti dell\'immagine prima di caricarla.',
        ], [
            'newAssetType' => 'tipo',
            'newAssetFile' => 'immagine',
        ]);

        $path = $this->newAssetFile->store("characters/{$this->character->tenant_id}/{$this->character->id}", 'local');

        $asset = CharacterAsset::create([
            'character_id' => $this->character->id,
            'type' => $this->newAssetType,
            'file_path' => $path,
            'source' => 'user_uploaded',
        ]);

        AssetUploadConsent::create([
            'character_asset_id' => $asset->id,
            'user_id' => auth()->id(),
            'consent_text_version' => AssetUploadConsentText::VERSION,
            'accepted_at' => now(),
        ]);

        $this->reset(['newAssetType', 'newAssetFile', 'newAssetConsent']);
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
            'assets' => $this->character->assets()->latest('id')->get(),
            'consentText' => AssetUploadConsentText::TEXT,
        ])->extends('layouts.dashboard');
    }
}
