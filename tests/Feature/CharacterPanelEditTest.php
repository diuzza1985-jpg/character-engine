<?php

namespace Tests\Feature;

use App\Livewire\CharacterPanel;
use App\Models\Character;
use App\Models\CharacterBibleSection;
use App\Models\CharacterVisualProfile;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CharacterPanelEditTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCharacterForNewUser(): array
    {
        $tenant = Tenant::create(['name' => 'Panel Test', 'email' => 'panel-edit-' . uniqid() . '@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Originale', 'one_liner' => 'Uno originale', 'slug' => 'originale-' . uniqid(), 'status' => 'draft']);

        CharacterBibleSection::create(['character_id' => $character->id, 'section_key' => 'valori', 'content' => 'Testo originale valori', 'version' => 1]);
        CharacterVisualProfile::create(['character_id' => $character->id, 'age_description' => 'Fascia originale']);

        return [$user, $character];
    }

    public function test_saving_identita_updates_the_character(): void
    {
        [$user, $character] = $this->makeCharacterForNewUser();

        Livewire::actingAs($user)->test(CharacterPanel::class)
            ->assertSet('name', 'Originale')
            ->set('name', 'Nome Nuovo')
            ->set('oneLiner', 'Una frase nuova')
            ->call('saveIdentita')
            ->assertHasNoErrors();

        $character->refresh();
        $this->assertSame('Nome Nuovo', $character->name);
        $this->assertSame('Una frase nuova', $character->one_liner);
    }

    public function test_saving_identita_requires_name_and_one_liner(): void
    {
        [$user, $character] = $this->makeCharacterForNewUser();

        Livewire::actingAs($user)->test(CharacterPanel::class)
            ->set('name', '')
            ->set('oneLiner', '')
            ->call('saveIdentita')
            ->assertHasErrors(['name', 'oneLiner']);

        $this->assertSame('Originale', $character->fresh()->name);
    }

    public function test_saving_personalita_updates_the_valori_bible_section(): void
    {
        [$user, $character] = $this->makeCharacterForNewUser();

        Livewire::actingAs($user)->test(CharacterPanel::class)
            ->assertSet('valoriContent', 'Testo originale valori')
            ->set('valoriContent', 'Testo aggiornato: curioso e generoso')
            ->call('savePersonalita')
            ->assertHasNoErrors();

        $section = CharacterBibleSection::where('character_id', $character->id)->where('section_key', 'valori')->first();
        $this->assertSame('Testo aggiornato: curioso e generoso', $section->content);
        $this->assertSame(2, $section->version, 'la versione deve incrementare a ogni salvataggio');
    }

    public function test_saving_umorismo_creates_the_bible_section_if_it_never_existed(): void
    {
        // Un personaggio "mai umoristico" potrebbe non avere affatto una sezione "umorismo"
        // salvata dal wizard (testo_overlay/joke_targets nulli) — il pannello deve poterla
        // comunque creare al primo salvataggio, non fallire perché la riga non esiste.
        [$user, $character] = $this->makeCharacterForNewUser();
        $this->assertNull(CharacterBibleSection::where('character_id', $character->id)->where('section_key', 'umorismo')->first());

        Livewire::actingAs($user)->test(CharacterPanel::class)
            ->set('umorismoContent', 'Personaggio serio, non scherza mai')
            ->call('saveUmorismo')
            ->assertHasNoErrors();

        $section = CharacterBibleSection::where('character_id', $character->id)->where('section_key', 'umorismo')->first();
        $this->assertNotNull($section);
        $this->assertSame('Personaggio serio, non scherza mai', $section->content);
    }

    public function test_saving_aspetto_updates_the_visual_profile(): void
    {
        [$user, $character] = $this->makeCharacterForNewUser();

        Livewire::actingAs($user)->test(CharacterPanel::class)
            ->assertSet('ageDescription', 'Fascia originale')
            ->set('ageDescription', '26-35, corporatura atletica')
            ->set('hairDescription', 'Castani, medi')
            ->call('saveAspetto')
            ->assertHasNoErrors();

        $profile = CharacterVisualProfile::where('character_id', $character->id)->first();
        $this->assertSame('26-35, corporatura atletica', $profile->age_description);
        $this->assertSame('Castani, medi', $profile->hair_description);
    }

    public function test_panel_without_a_character_still_shows_working_credit_gate(): void
    {
        $tenant = Tenant::create(['name' => 'Senza Personaggio', 'email' => 'senza-personaggio@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        $response = $this->actingAs($user)->get(route('character.panel'));

        $response->assertOk();
        $response->assertSee('completa il');
        $response->assertSee('Genera il volto e i contenuti del tuo personaggio');
    }
}
