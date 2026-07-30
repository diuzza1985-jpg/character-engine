<?php

namespace App\Livewire;

use App\Models\AssetUploadConsent;
use App\Models\Character;
use App\Models\CharacterAsset;
use App\Models\CharacterDraft;
use App\Models\CharacterKinship;
use App\Models\CharacterRelationship;
use App\Models\CharacterVisualProfile;
use App\Services\ConvertCharacterDraftToCharacter;
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

    // Personaggio "del mondo" (Fase 2 mondo narrativo, kind persona|animale, nessuna draft
    // propria): campi di modifica leggera, solo quelli raccolti alla creazione — mai il
    // questionario completo, pensato per il personaggio principale.
    public string $editNome = '';
    public string $editRelazione = '';
    public string $editDescrizione = '';
    public string $editSpecie = '';
    public string $editRazza = '';
    public string $editAspetto = '';
    public string $editCarattere = '';
    public ?string $worldCharacterOwnerName = null;

    public function mount(Character $character): void
    {
        abort_unless($character->tenant_id === auth()->user()->tenant_id, 403);
        $this->character = $character;

        if ($this->character->kind) {
            $this->hydrateWorldCharacterFields();
        }
    }

    /**
     * La draft che ha dichiarato questo personaggio (character_id nel suo world_characters, non
     * la SUA draft — non ne ha una propria) è l'unica fonte affidabile dei campi originali: cercarla
     * tra tutte le draft convertite del tenant, non assumere che esista una scorciatoia più diretta.
     */
    private function findOwningDraftAndItemIndex(): array
    {
        $drafts = CharacterDraft::where('tenant_id', $this->character->tenant_id)
            ->whereNotNull('character_id')
            ->get();

        foreach ($drafts as $draft) {
            foreach ($draft->world_characters ?? [] as $index => $item) {
                if (($item['character_id'] ?? null) === $this->character->id) {
                    return [$draft, $index];
                }
            }
        }

        return [null, null];
    }

    private function hydrateWorldCharacterFields(): void
    {
        [$draft, $index] = $this->findOwningDraftAndItemIndex();
        $item = $draft ? ($draft->world_characters[$index] ?? []) : [];

        $this->editNome = $item['nome'] ?? $this->character->name;
        $this->editRelazione = $item['relazione'] ?? '';
        $this->editDescrizione = $item['descrizione'] ?? '';
        $this->editSpecie = $item['specie'] ?? '';
        $this->editRazza = $item['razza'] ?? '';
        $this->editAspetto = $item['aspetto'] ?? '';
        $this->editCarattere = $item['carattere'] ?? '';
        $this->worldCharacterOwnerName = $draft?->character?->name;
    }

    /**
     * Salva le modifiche di un personaggio "del mondo" — mai il questionario completo. Se troviamo
     * la draft che lo ha dichiarato, aggiorniamo la sua voce in world_characters e riconvertiamo
     * (stessa ConvertCharacterDraftToCharacter del wizard: profilo visivo, relazioni, propagazione
     * in famiglia restano coerenti, nessuna logica duplicata qui). Se non la troviamo (dati orfani,
     * caso limite), aggiorniamo comunque i campi diretti perché la modifica non vada persa.
     */
    public function updateWorldCharacterFields(): void
    {
        abort_unless($this->character->kind !== null, 404);

        if ($this->character->kind === 'animale') {
            $this->validate([
                'editNome' => ['required', 'string', 'max:255'],
                'editSpecie' => ['required', 'string', 'max:255'],
            ], [], ['editNome' => 'nome', 'editSpecie' => 'specie']);
        } else {
            $this->validate([
                'editNome' => ['required', 'string', 'max:255'],
                'editRelazione' => ['required', 'string', 'in:' . implode(',', CharacterKinship::RELATIONSHIP_TYPES)],
            ], [], ['editNome' => 'nome', 'editRelazione' => 'tipo di relazione']);
        }

        [$draft, $index] = $this->findOwningDraftAndItemIndex();

        if ($draft !== null) {
            $items = $draft->world_characters;
            $items[$index] = $this->character->kind === 'animale'
                ? ['tipo' => 'animale', 'nome' => $this->editNome, 'specie' => $this->editSpecie, 'razza' => $this->editRazza, 'aspetto' => $this->editAspetto, 'carattere' => $this->editCarattere, 'character_id' => $this->character->id]
                : ['tipo' => 'persona', 'nome' => $this->editNome, 'relazione' => $this->editRelazione, 'descrizione' => $this->editDescrizione, 'character_id' => $this->character->id];
            $draft->world_characters = $items;
            $draft->save();

            app(ConvertCharacterDraftToCharacter::class)->convert($draft, $this->character->tenant_id);
        } else {
            $this->character->name = $this->editNome;
            $this->character->one_liner = $this->character->kind === 'animale'
                ? trim($this->editSpecie.($this->editRazza !== '' ? " ({$this->editRazza})" : '').($this->editCarattere !== '' ? " — {$this->editCarattere}" : ''))
                : $this->editDescrizione;
            $this->character->save();

            $faceDescription = $this->character->kind === 'animale' ? $this->editAspetto : $this->editDescrizione;
            if ($faceDescription !== '') {
                CharacterVisualProfile::updateOrCreate(['character_id' => $this->character->id], ['face_description' => $faceDescription]);
            }

            $newType = $this->character->kind === 'animale' ? $this->editSpecie : $this->editRelazione;
            CharacterRelationship::where('related_character_id', $this->character->id)->update(['name' => $this->editNome, 'type' => $newType]);
        }

        $this->character->refresh();
        $this->hydrateWorldCharacterFields();
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

        // Senza questo, una parentela creata qui (due Character già esistenti, collegati dopo,
        // non tramite il "mondo" di qualcuno) restava visibile solo nella tab Parentele — invisibile
        // al cervello editoriale di entrambi (relazioni_rilevanti) e quindi mai utilizzabile per
        // nominarli nei contenuti o comparire insieme in foto (Fase 3), a differenza di chi viene
        // collegato tramite syncWorldCharacters(). Le due tabelle hanno convenzioni diverse per
        // 'type', verificato sui dati reali (non assunto): CharacterKinship.relationship_type
        // descrive cosa È character_id ("io sono ___", come lo compila chi usa il form dalla
        // propria pagina); CharacterRelationship.type descrive cosa È related_character_id
        // rispetto a character_id — va quindi invertito da un lato e lasciato diretto dall'altro.
        $inverseType = CharacterKinship::inverseLabelFor($this->newRelationshipType);
        CharacterRelationship::updateOrCreate(
            ['character_id' => $this->character->id, 'related_character_id' => $related->id],
            ['name' => $related->name, 'type' => $inverseType]
        );
        CharacterRelationship::updateOrCreate(
            ['character_id' => $related->id, 'related_character_id' => $this->character->id],
            ['name' => $this->character->name, 'type' => $this->newRelationshipType]
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
