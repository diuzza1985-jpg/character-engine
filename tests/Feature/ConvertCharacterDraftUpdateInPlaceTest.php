<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterBibleSection;
use App\Models\CharacterDraft;
use App\Models\CharacterEditorialSettings;
use App\Models\CharacterRelationship;
use App\Models\Tenant;
use App\Services\ConvertCharacterDraftToCharacter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ConvertCharacterDraftUpdateInPlaceTest extends TestCase
{
    use DatabaseTransactions;

    private function baseDraftAttributes(): array
    {
        return [
            'session_token' => 'update-in-place-' . uniqid(),
            'goal' => 'Intrattenere',
            'niche' => ['Tecnologia'],
            'name' => 'Prova',
            'one_liner' => 'Un personaggio di prova',
            'traits' => ['curioso'],
            'core_values' => ['Libertà'],
            'dislikes' => ['Ritardi'],
            'humor_level' => 'leggero',
            'joke_targets' => ['Lavoro', 'Tecnologia', 'Traffico', 'Cibo', 'Burocrazia'],
            'humor_safe_topics' => ['Aspetto fisico'],
            'age_range' => '26-35',
            'presentation' => 'Femminile',
            'style_archetype' => 'Casual',
        ];
    }

    public function test_reconverting_the_same_draft_updates_the_existing_character_without_duplicating_rows(): void
    {
        $tenant = Tenant::create(['name' => 'Update Test', 'email' => 'update-inplace@example.com', 'status' => 'trial']);
        $draft = CharacterDraft::create($this->baseDraftAttributes());

        $converter = app(ConvertCharacterDraftToCharacter::class);
        $character = $converter->convert($draft, $tenant->id);
        $firstCharacterId = $character->id;

        $this->assertSame(1, Character::where('tenant_id', $tenant->id)->count());
        $valoriBefore = CharacterBibleSection::where('character_id', $character->id)->where('section_key', 'valori')->first();
        $this->assertSame(1, $valoriBefore->version);

        // L'utente riapre il questionario e cambia qualcosa...
        $draft->refresh();
        $draft->update(['one_liner' => 'Frase aggiornata', 'traits' => ['curioso', 'generoso']]);

        $character2 = $converter->convert($draft, $tenant->id);

        // Stesso Character, nessun duplicato.
        $this->assertSame($firstCharacterId, $character2->id);
        $this->assertSame(1, Character::where('tenant_id', $tenant->id)->count());
        $this->assertSame(1, CharacterEditorialSettings::where('character_id', $character2->id)->count());

        $character2->refresh();
        $this->assertSame('Frase aggiornata', $character2->one_liner);

        $valoriAfter = CharacterBibleSection::where('character_id', $character2->id)->where('section_key', 'valori')->first();
        $this->assertSame(1, CharacterBibleSection::where('character_id', $character2->id)->where('section_key', 'valori')->count(), 'niente sezioni bible duplicate');
        $this->assertSame(2, $valoriAfter->version, 'la versione deve incrementare, non ripartire da 1');
        $this->assertStringContainsString('generoso', $valoriAfter->content);

        $draft->refresh();
        $this->assertSame($firstCharacterId, $draft->character_id);
        $this->assertSame('convertito', $draft->status);
    }

    public function test_reconverting_preserves_admin_only_editorial_settings(): void
    {
        $tenant = Tenant::create(['name' => 'Preserve Test', 'email' => 'preserve-settings@example.com', 'status' => 'trial']);
        $draft = CharacterDraft::create($this->baseDraftAttributes());

        $converter = app(ConvertCharacterDraftToCharacter::class);
        $character = $converter->convert($draft, $tenant->id);

        // Un admin personalizza un valore che NON fa parte del questionario.
        CharacterEditorialSettings::where('character_id', $character->id)->update(['max_giorni_silenzio' => 7]);

        $draft->refresh();
        $draft->update(['goal' => 'Educare']);
        $converter->convert($draft, $tenant->id);

        $settings = CharacterEditorialSettings::where('character_id', $character->id)->first();
        $this->assertSame(7, $settings->max_giorni_silenzio, 'un riepilogo non deve mai riportare ai default un valore già personalizzato via admin');
        $this->assertSame('Educare', $settings->goal, 'i campi del questionario devono comunque aggiornarsi');
    }

    public function test_key_relationships_populate_character_relationship(): void
    {
        $tenant = Tenant::create(['name' => 'Relations Test', 'email' => 'relations-test@example.com', 'status' => 'trial']);
        $draft = CharacterDraft::create(array_merge($this->baseDraftAttributes(), [
            'key_relationships' => [
                ['nome' => 'Fernando', 'relazione' => 'marito', 'tratto' => 'sempre a un progetto dal disastro'],
                ['nome' => 'Anna', 'relazione' => 'sorella', 'tratto' => 'vive a Milano'],
                ['nome' => '', 'relazione' => 'ignorato', 'tratto' => 'nome vuoto, deve essere saltato'],
            ],
        ]));

        $character = app(ConvertCharacterDraftToCharacter::class)->convert($draft, $tenant->id);

        $this->assertSame(2, CharacterRelationship::where('character_id', $character->id)->count());
        $fernando = CharacterRelationship::where('character_id', $character->id)->where('name', 'Fernando')->first();
        $this->assertSame('marito', $fernando->type);
        $this->assertSame('sempre a un progetto dal disastro', $fernando->notes);
    }

    public function test_biografia_section_is_generated_from_approfondimento_fields(): void
    {
        $tenant = Tenant::create(['name' => 'Bio Test', 'email' => 'bio-test@example.com', 'status' => 'trial']);
        $draft = CharacterDraft::create(array_merge($this->baseDraftAttributes(), [
            'backstory' => 'Cresciuta in un piccolo paese di montagna.',
            'life_goals' => 'Aprire un giorno un piccolo studio tutto suo.',
            'fears' => ['paura del giudizio', 'paura di restare indietro'],
            'typical_phrases' => ['Cinque minuti e torno.'],
        ]));

        $character = app(ConvertCharacterDraftToCharacter::class)->convert($draft, $tenant->id);

        $bio = CharacterBibleSection::where('character_id', $character->id)->where('section_key', 'biografia')->first();
        $this->assertNotNull($bio);
        $this->assertStringContainsString('Cresciuta in un piccolo paese', $bio->content);
        $this->assertStringContainsString('paura del giudizio', $bio->content);
        $this->assertStringContainsString('Cinque minuti e torno', $bio->content);
    }

    public function test_biografia_section_has_sensible_default_when_approfondimento_is_empty(): void
    {
        $tenant = Tenant::create(['name' => 'Bio Empty Test', 'email' => 'bio-empty-test@example.com', 'status' => 'trial']);
        $draft = CharacterDraft::create($this->baseDraftAttributes());

        $character = app(ConvertCharacterDraftToCharacter::class)->convert($draft, $tenant->id);

        $bio = CharacterBibleSection::where('character_id', $character->id)->where('section_key', 'biografia')->first();
        $this->assertNotNull($bio);
        $this->assertNotEmpty(trim($bio->content));
    }
}
