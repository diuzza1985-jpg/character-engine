<?php

namespace Tests\Feature;

use App\Mail\ComicTargetRepetitionMail;
use App\Models\Character;
use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * character:check-comic-targets: classifica solo i post pubblicati mai visti, scarta i bersagli
 * inventati dal modello e avvisa quando lo stesso bersaglio torna nella finestra.
 */
class CheckComicTargetsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_classifies_new_posts_and_alerts_on_repetition(): void
    {
        Storage::fake('local');
        Mail::fake();
        Config::set('mail.alert_to', 'alert@example.com');
        $slug = 'comic-test-' . uniqid();
        Config::set("comic_targets.characters.{$slug}", ['il caffè', 'password dimenticate']);

        $tenant = Tenant::create(['name' => 'Comic Test', 'email' => $slug . '@example.com', 'status' => 'trial']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Comica', 'slug' => $slug, 'status' => 'active']);
        $make = fn (string $caption, array $extra = []) => Post::create(array_merge([
            'character_id' => $character->id, 'platform' => 'instagram', 'media_type' => 'image',
            'status' => 'published', 'caption' => $caption, 'published_at' => now()->subDays(2),
        ], $extra));

        $a = $make('Terzo caffè e sono ancora le 9');
        $b = $make('Il caffè è finito, panico');
        $c = $make('Bersaglio inventato');
        $already = $make('Già analizzato', ['comic_target' => 'password dimenticate', 'comic_target_checked_at' => now()]);
        $draft = $make('Bozza', ['status' => 'draft']);

        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => json_encode(['posts' => [
            ['id' => $a->id, 'target' => 'il caffè'],
            ['id' => $b->id, 'target' => 'il caffè'],
            ['id' => $c->id, 'target' => 'gli unicorni'],
        ]])]]]])]);

        $this->artisan('character:check-comic-targets', ['slug' => $slug])->assertSuccessful();

        $this->assertSame('il caffè', $a->fresh()->comic_target);
        $this->assertNull($c->fresh()->comic_target);
        $this->assertNotNull($c->fresh()->comic_target_checked_at);
        $this->assertNull($draft->fresh()->comic_target_checked_at);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => ! str_contains($request['messages'][0]['content'], 'Già analizzato'));
        Mail::assertSent(ComicTargetRepetitionMail::class, fn ($mail) => $mail->repetitions->keys()->all() === ['il caffè']);
        Storage::disk('local')->assertExists("reports/bersagli-comici-{$slug}.md");
    }
}
