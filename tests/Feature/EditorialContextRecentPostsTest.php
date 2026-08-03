<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Generation;
use App\Models\Post;
use App\Models\Tenant;
use App\Services\EditorialBrainService;
use App\Services\EditorialContextBuilder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Bug reale osservato in produzione (agosto 2026): il cervello editoriale non aveva nessuna
 * visibilità su cosa avesse mostrato VISIVAMENTE nei post recenti (solo sul testo delle caption)
 * — stesso abbigliamento ("shirt and jeans") e stessa scena da tavolo ripetuti quasi identici
 * per settimane. Questi test verificano solo la parte deterministica (context builder + rendering
 * del prompt), pura lettura DB/stringhe, senza nessuna chiamata GPT reale.
 */
class EditorialContextRecentPostsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_recent_posts_include_the_image_prompt_from_their_generation(): void
    {
        $tenant = Tenant::create(['name' => 'Recent Posts Test', 'email' => 'recent-posts-test@example.com', 'status' => 'trial']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Protagonista', 'slug' => 'protagonista-' . uniqid(), 'status' => 'active']);

        $generation = Generation::create([
            'character_id' => $character->id,
            'tenant_id' => $tenant->id,
            'purpose' => 'instagram_post',
            'status' => 'completed',
            'input' => [],
            'output' => ['prompt_immagine' => 'A woman wearing a light shirt and jeans at a desk with iced coffee.'],
            'credits_charged' => 0,
        ]);

        Post::create(['character_id' => $character->id, 'generation_id' => $generation->id, 'platform' => 'instagram', 'media_type' => 'image', 'status' => 'published', 'caption' => 'Una caption qualsiasi']);

        $context = app(EditorialContextBuilder::class)->build($character);
        $recent = $context['contenuti_recenti'][0];

        $this->assertSame('A woman wearing a light shirt and jeans at a desk with iced coffee.', $recent['prompt_immagine']);
    }

    public function test_the_rendered_prompt_shows_recent_image_prompts_for_visual_variety(): void
    {
        $tenant = Tenant::create(['name' => 'Visual Variety Test', 'email' => 'visual-variety-test@example.com', 'status' => 'trial']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Protagonista', 'slug' => 'protagonista-' . uniqid(), 'status' => 'active']);

        $generation = Generation::create([
            'character_id' => $character->id,
            'tenant_id' => $tenant->id,
            'purpose' => 'instagram_post',
            'status' => 'completed',
            'input' => [],
            'output' => ['prompt_immagine' => 'A woman wearing a light shirt and jeans at a desk with iced coffee.'],
            'credits_charged' => 0,
        ]);
        Post::create(['character_id' => $character->id, 'generation_id' => $generation->id, 'platform' => 'instagram', 'media_type' => 'image', 'status' => 'published', 'caption' => 'Una caption qualsiasi']);

        $context = app(EditorialContextBuilder::class)->build($character);
        $prompt = app(EditorialBrainService::class)->lastPrompt($character, $context);

        $this->assertStringContainsString('A woman wearing a light shirt and jeans at a desk with iced coffee.', $prompt);
        $this->assertStringContainsString('VARIETÀ VISIVA', $prompt);
    }
}
