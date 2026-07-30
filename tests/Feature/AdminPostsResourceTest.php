<?php

namespace Tests\Feature;

use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Models\Character;
use App\Models\Post;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Gap reale segnalato dall'utente: /aiadmin/scheduled-posts elenca solo gli slot programmati
 * (scheduled_posts, quasi sempre vuota), non i Post realmente generati — non esisteva alcuna
 * vista per vedere lo storico completo dei contenuti prodotti (project.md sez. 12 punto 6).
 */
class AdminPostsResourceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_the_posts_list_shows_every_generated_post(): void
    {
        $tenant = Tenant::create(['name' => 'Admin Posts Test', 'email' => 'admin-posts-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Personaggio Test', 'slug' => 'personaggio-test-' . uniqid(), 'status' => 'active']);

        Post::create(['character_id' => $character->id, 'platform' => 'instagram', 'media_type' => 'image', 'status' => 'draft', 'caption' => 'Una caption di prova mai pubblicata']);
        Post::create(['character_id' => $character->id, 'platform' => 'instagram', 'media_type' => 'carousel', 'status' => 'published', 'caption' => 'Una caption già pubblicata', 'platform_post_id' => '999']);

        $response = $this->actingAs($user)->get('/aiadmin/posts');

        $response->assertOk();
        $response->assertSee('Personaggio Test');
        $response->assertSee('Una caption di prova mai pubblicata');
        $response->assertSee('Una caption già pubblicata');
    }

    public function test_filtering_by_character_only_shows_its_own_posts(): void
    {
        $tenant = Tenant::create(['name' => 'Filter Test', 'email' => 'filter-posts-test@example.com', 'status' => 'trial']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $characterA = Character::create(['tenant_id' => $tenant->id, 'name' => 'Personaggio A', 'slug' => 'personaggio-a-' . uniqid(), 'status' => 'active']);
        $characterB = Character::create(['tenant_id' => $tenant->id, 'name' => 'Personaggio B', 'slug' => 'personaggio-b-' . uniqid(), 'status' => 'active']);

        Post::create(['character_id' => $characterA->id, 'platform' => 'instagram', 'media_type' => 'image', 'status' => 'draft', 'caption' => 'Contenuto A']);
        Post::create(['character_id' => $characterB->id, 'platform' => 'instagram', 'media_type' => 'image', 'status' => 'draft', 'caption' => 'Contenuto B']);

        Livewire::actingAs($user)->test(ListPosts::class)
            ->set('tableFilters.character_id.value', $characterA->id)
            ->assertSee('Contenuto A')
            ->assertDontSee('Contenuto B');
    }
}
