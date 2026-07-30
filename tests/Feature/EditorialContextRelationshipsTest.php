<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\CharacterRelationship;
use App\Models\CharacterVisualProfile;
use App\Models\Tenant;
use App\Services\EditorialContextBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EditorialContextRelationshipsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Bug reale osservato in produzione: un'immagine multi-soggetto (Fase 3) generata per una
     * relazione con capelli lunghi (foto di riferimento e profilo visivo concordi) è uscita con
     * capelli corti — il prompt non includeva alcun rinforzo testuale sull'aspetto fisico della
     * persona coinvolta, solo la foto di riferimento passata al modello di generazione.
     */
    public function test_a_relationship_linked_to_a_real_character_includes_its_visual_profile(): void
    {
        $tenant = Tenant::create(['name' => 'Visual Context Test', 'email' => 'visual-context-test@example.com', 'status' => 'trial']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Protagonista', 'slug' => 'protagonista-' . uniqid(), 'status' => 'active']);
        $daughter = Character::create(['tenant_id' => $tenant->id, 'name' => 'Figlia', 'slug' => 'figlia-' . uniqid(), 'status' => 'draft']);

        CharacterVisualProfile::create([
            'character_id' => $daughter->id,
            'hair_description' => 'Castano, Lunghi, Lisci.',
            'face_description' => 'Occhi Marroni.',
        ]);

        CharacterRelationship::create([
            'character_id' => $character->id,
            'related_character_id' => $daughter->id,
            'name' => 'Figlia',
            'type' => 'figlia',
        ]);

        $context = app(EditorialContextBuilder::class)->build($character);
        $relazione = collect($context['relazioni_rilevanti'])->firstWhere('nome', 'Figlia');

        $this->assertNotNull($relazione);
        $this->assertTrue($relazione['puo_comparire_in_foto']);
        $this->assertSame('Castano, Lunghi, Lisci. Occhi Marroni.', $relazione['aspetto_visivo']);
    }

    public function test_a_relationship_without_a_linked_character_has_no_visual_summary(): void
    {
        $tenant = Tenant::create(['name' => 'No Visual Test', 'email' => 'no-visual-test@example.com', 'status' => 'trial']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Protagonista', 'slug' => 'protagonista-' . uniqid(), 'status' => 'active']);

        CharacterRelationship::create([
            'character_id' => $character->id,
            'name' => 'Un cliente citato una volta',
            'type' => 'cliente',
        ]);

        $context = app(EditorialContextBuilder::class)->build($character);
        $relazione = collect($context['relazioni_rilevanti'])->firstWhere('nome', 'Un cliente citato una volta');

        $this->assertNotNull($relazione);
        $this->assertFalse($relazione['puo_comparire_in_foto']);
        $this->assertNull($relazione['aspetto_visivo']);
    }

    public function test_a_linked_character_without_a_visual_profile_has_no_visual_summary(): void
    {
        $tenant = Tenant::create(['name' => 'No Profile Test', 'email' => 'no-profile-test@example.com', 'status' => 'trial']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Protagonista', 'slug' => 'protagonista-' . uniqid(), 'status' => 'active']);
        $related = Character::create(['tenant_id' => $tenant->id, 'name' => 'Collegato', 'slug' => 'collegato-' . uniqid(), 'status' => 'draft']);

        CharacterRelationship::create([
            'character_id' => $character->id,
            'related_character_id' => $related->id,
            'name' => 'Collegato',
            'type' => 'amico_a',
        ]);

        $context = app(EditorialContextBuilder::class)->build($character);
        $relazione = collect($context['relazioni_rilevanti'])->firstWhere('nome', 'Collegato');

        $this->assertTrue($relazione['puo_comparire_in_foto']);
        $this->assertNull($relazione['aspetto_visivo']);
    }
}
