<?php

namespace Tests\Feature;

use App\Jobs\PublishInstagramPostJob;
use App\Models\Character;
use App\Models\Post;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    /**
     * Bug reale osservato: questa macchina di sviluppo (WSL2/Windows) si è sospesa per oltre 2
     * giorni — un post rimasto in coda da così tanto tempo scriveva un contenuto pensato per un
     * orario preciso (es. "buonasera") che pubblicato ore/giorni dopo tradirebbe il principio
     * cardine di questa automazione. Verificato che un post troppo stantio (oltre la soglia) NON
     * arriva nemmeno a tentare la pubblicazione — distinto dal warning specifico di
     * PublishInstagramPostJob (account non connesso) tramite il testo del log.
     */
    public function test_a_post_scheduled_too_long_ago_is_discarded_instead_of_published(): void
    {
        Log::spy();
        $character = $this->makeCharacter();

        $stale = Post::create(['character_id' => $character->id, 'platform' => 'instagram', 'media_type' => 'image', 'status' => 'draft', 'caption' => 'Post stantio', 'scheduled_at' => now()->subHours(30)]);

        $this->artisan('character:publish-due-posts')->assertExitCode(0);

        $this->assertSame('failed', $stale->fresh()->status);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'scartato') && str_contains($message, (string) $stale->id))->once();
    }

    /**
     * Bug reale corretto il 3 settembre 2026 (Post#50): PublishInstagramPostJob dispatchava con
     * dispatchSync(), che ignora $tries/$backoff — un errore transitorio di Meta falliva il post
     * al primo tentativo, mai ritentato, perché nessun queue worker girava da nessuna parte.
     * Verificato qui che il comando ora accodi davvero il job (QUEUE_CONNECTION=database, come in
     * produzione) invece di eseguirlo sul posto: il post deve restare 'draft' subito dopo — sarà
     * il worker reale (servizio `queue` in compose.yaml) a processarlo, coi retry già scritti sul
     * job. In test normalmente gira QUEUE_CONNECTION=sync (phpunit.xml): qui lo sovrascriviamo
     * apposta per verificare il comportamento reale di produzione, non quello di test.
     */
    public function test_dispatches_the_publish_job_onto_the_real_queue_instead_of_running_it_inline(): void
    {
        Config::set('queue.default', 'database');
        $character = $this->makeCharacter();

        $due = Post::create(['character_id' => $character->id, 'platform' => 'instagram', 'media_type' => 'image', 'status' => 'draft', 'caption' => 'Post in orario', 'scheduled_at' => now()->subMinutes(5)]);

        $this->artisan('character:publish-due-posts')->assertExitCode(0);

        // Non ancora processato inline: è il worker reale a doverlo fare, non questo comando.
        $this->assertSame('draft', $due->fresh()->status);

        $queued = DB::table('jobs')->where('queue', 'default')->get();
        $this->assertCount(1, $queued);
        $payload = json_decode($queued->first()->payload, true);
        $this->assertSame(PublishInstagramPostJob::class, $payload['displayName']);
        $this->assertStringContainsString("postId\";i:{$due->id};", $payload['data']['command']);
    }

    public function test_does_nothing_when_no_post_is_due(): void
    {
        $character = $this->makeCharacter();
        $future = Post::create(['character_id' => $character->id, 'platform' => 'instagram', 'media_type' => 'image', 'status' => 'draft', 'caption' => 'Post futuro', 'scheduled_at' => now()->addHour()]);

        $this->artisan('character:publish-due-posts')->assertExitCode(0);

        $this->assertSame('draft', $future->fresh()->status);
    }
}
