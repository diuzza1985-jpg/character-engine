<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Automazione giornaliera: character:publish-due-posts deve trovare SOLO i post in bozza con
 * scheduled_at già passato, e nessun altro. Il personaggio di test non ha un SocialAccount
 * connesso: PublishInstagramPostJob (verificato nel codice reale, non mockato) in quel caso
 * marca il post 'failed' senza lanciare eccezioni — un percorso di fallimento reale, non un
 * doppio, che basta a verificare che il post SIA STATO SELEZIONATO E PROCESSATO, senza dover
 * mockare una vera chiamata a Instagram.
 */
class PublishDuePostsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeCharacter(): Character
    {
        $tenant = Tenant::create(['name' => 'Publish Due Test', 'email' => 'publish-due-test-' . uniqid() . '@example.com', 'status' => 'trial']);

        return Character::create(['tenant_id' => $tenant->id, 'name' => 'Personaggio Test', 'slug' => 'personaggio-test-' . uniqid(), 'status' => 'active']);
    }

    public function test_publishes_only_draft_posts_whose_scheduled_time_has_arrived(): void
    {
        $character = $this->makeCharacter();

        $due = Post::create(['character_id' => $character->id, 'platform' => 'instagram', 'media_type' => 'image', 'status' => 'draft', 'caption' => 'Post in orario', 'scheduled_at' => now()->subMinutes(5)]);
        $future = Post::create(['character_id' => $character->id, 'platform' => 'instagram', 'media_type' => 'image', 'status' => 'draft', 'caption' => 'Post futuro', 'scheduled_at' => now()->addHours(3)]);
        $noSchedule = Post::create(['character_id' => $character->id, 'platform' => 'instagram', 'media_type' => 'image', 'status' => 'draft', 'caption' => 'Senza orario']);
        $alreadyPublished = Post::create(['character_id' => $character->id, 'platform' => 'instagram', 'media_type' => 'image', 'status' => 'published', 'caption' => 'Già pubblicato', 'scheduled_at' => now()->subDay()]);

        $this->artisan('character:publish-due-posts')->assertExitCode(0);

        // Selezionato e processato: nessun SocialAccount collegato -> fallisce in modo reale,
        // ma il fatto che sia 'failed' (non più 'draft') prova che è STATO selezionato.
        $this->assertSame('failed', $due->fresh()->status);

        // Nessuno di questi doveva essere toccato.
        $this->assertSame('draft', $future->fresh()->status);
        $this->assertSame('draft', $noSchedule->fresh()->status);
        $this->assertSame('published', $alreadyPublished->fresh()->status);
    }

    public function test_does_nothing_when_no_post_is_due(): void
    {
        $character = $this->makeCharacter();
        $future = Post::create(['character_id' => $character->id, 'platform' => 'instagram', 'media_type' => 'image', 'status' => 'draft', 'caption' => 'Post futuro', 'scheduled_at' => now()->addHour()]);

        $this->artisan('character:publish-due-posts')->assertExitCode(0);

        $this->assertSame('draft', $future->fresh()->status);
    }
}
