<?php

namespace App\Livewire;

use App\Models\Character;
use App\Models\CharacterBibleSection;
use Livewire\Component;

/**
 * Pannello post-registrazione (/il-mio-personaggio): a differenza del wizard pubblico, qui
 * l'utente è già autenticato e proprietario del personaggio — ogni tab legge/scrive
 * direttamente sui modelli reali (CharacterBibleSection per le sezioni in prosa, non sui campi
 * strutturati del wizard, che non vengono conservati dopo la conversione — spec tecnica §2:
 * "più veloce e meno rischioso generare qui il testo prosa che l'EditorialContextBuilder già si
 * aspetta"). Stesso pattern già in uso in Filament (BibleSectionsRelationManager): sezione =
 * section_key + content testuale libero.
 */
class CharacterPanel extends Component
{
    public ?Character $character = null;
    public int $creditBalance = 0;
    public string $tab = 'identita';

    public string $name = '';
    public string $oneLiner = '';

    public string $valoriContent = '';
    public string $voceContent = '';
    public string $umorismoContent = '';
    public string $famigliaContent = '';

    public string $ageDescription = '';
    public string $faceDescription = '';
    public string $hairDescription = '';
    public string $wardrobeNotes = '';
    public string $visualRulesText = '';

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;
        $this->character = $tenant?->characters()->latest('id')->first();
        $this->creditBalance = $tenant?->creditBalance() ?? 0;

        if (! $this->character) {
            return;
        }

        $this->name = $this->character->name ?? '';
        $this->oneLiner = $this->character->one_liner ?? '';

        $sections = CharacterBibleSection::where('character_id', $this->character->id)->pluck('content', 'section_key');
        $this->valoriContent = $sections->get('valori', '');
        $this->voceContent = $sections->get('voce', '');
        $this->umorismoContent = $sections->get('umorismo', '');
        $this->famigliaContent = $sections->get('famiglia', '');

        $profile = $this->character->visualProfile;
        $this->ageDescription = $profile->age_description ?? '';
        $this->faceDescription = $profile->face_description ?? '';
        $this->hairDescription = $profile->hair_description ?? '';
        $this->wardrobeNotes = $profile->wardrobe_notes ?? '';
        $this->visualRulesText = $profile->visual_rules_text ?? '';
    }

    public function saveIdentita(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'oneLiner' => ['required', 'string', 'max:120'],
        ], [], ['name' => 'nome', 'oneLiner' => '"in una frase, chi è"']);

        $this->character->update(['name' => $this->name, 'one_liner' => $this->oneLiner]);
        session()->flash('panel_saved', 'identita');
    }

    public function savePersonalita(): void
    {
        $this->saveBibleSection('valori', $this->valoriContent, 'personalita');
    }

    public function saveVoce(): void
    {
        $this->saveBibleSection('voce', $this->voceContent, 'voce');
    }

    public function saveUmorismo(): void
    {
        $this->saveBibleSection('umorismo', $this->umorismoContent, 'umorismo');
    }

    public function saveVita(): void
    {
        $this->saveBibleSection('famiglia', $this->famigliaContent, 'vita');
    }

    private function saveBibleSection(string $sectionKey, string $content, string $tabKey): void
    {
        $section = CharacterBibleSection::firstOrNew([
            'character_id' => $this->character->id,
            'section_key' => $sectionKey,
        ]);
        $section->content = $content;
        $section->version = ($section->version ?? 0) + 1;
        $section->save();

        session()->flash('panel_saved', $tabKey);
    }

    public function saveAspetto(): void
    {
        $this->character->visualProfile()->updateOrCreate(
            ['character_id' => $this->character->id],
            [
                'age_description' => $this->ageDescription,
                'face_description' => $this->faceDescription,
                'hair_description' => $this->hairDescription,
                'wardrobe_notes' => $this->wardrobeNotes,
                'visual_rules_text' => $this->visualRulesText,
            ]
        );

        session()->flash('panel_saved', 'aspetto');
    }

    public function render()
    {
        // ->extends(), non ->layout(): layouts.public usa @yield('content') (meccanismo Blade
        // @extends/@section), diverso da layouts.wizard che usa {{ $slot }} (meccanismo
        // @component, quello dietro ->layout()) — i due metodi Livewire non sono intercambiabili.
        return view('livewire.character-panel')->extends('layouts.public');
    }
}
