<?php

namespace Tests\Feature;

use App\Jobs\PublishInstagramPostJob;
use App\Models\Character;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\Tenant;
use App\Services\InstagramGraphService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Bug reale osservato in produzione il 2 settembre 2026 (Post#50): un errore transitorio di Meta
 * ("Media ID is not available", 9007/2207027 — il container immagine non era ancora pronto quando
 * si è chiamato media_publish) falliva il post al primo e unico tentativo. Due cause reali
 * trovate verificando il codice, non assunte: (1) il polling dello stato del container esisteva
 * già (waitUntilReady) ma con un timeout troppo corto (30s totali) per i tempi di elaborazione
 * reali di Meta; (2) il job veniva dispatchato con dispatchSync(), che ignora del tutto
 * $tries/$backoff — nessun queue worker girava da nessuna parte (verificato con "docker compose
 * ps"), quindi il ritentativo scritto sul job non scattava mai.
 *
 * Sleep::fake() qui sotto: niente attese reali (il nuovo polling arriva a 4 minuti di sleep nel
 * caso peggiore), stesso principio già seguito per la "resilienza a un ambiente che si sospende"
 * (11.26, verificato via reflection sulla logica pura senza tempo reale).
 */
class PublishInstagramPostJobTest extends TestCase
{
    use DatabaseTransactions;

    private function makeConnectedPost(string $mediaType = 'story'): Post
    {
        $tenant = Tenant::create(['name' => 'Publish Job Test', 'email' => 'publish-job-test-' . uniqid() . '@example.com', 'status' => 'trial']);
        $character = Character::create(['tenant_id' => $tenant->id, 'name' => 'Personaggio Test', 'slug' => 'personaggio-test-' . uniqid(), 'status' => 'active']);
        SocialAccount::create([
            'character_id' => $character->id,
            'platform' => 'instagram',
            'ig_user_id' => 'IGUSER1',
            'access_token' => 'token-di-test',
            'status' => 'connected',
        ]);

        return Post::create([
            'character_id' => $character->id,
            'platform' => 'instagram',
            'media_type' => $mediaType,
            'status' => 'draft',
            'caption' => 'Post di test',
            'media_urls' => ['generations/test/fake.png'],
        ]);
    }

    /**
     * Verifica diretta della logica pura di waitUntilReady() via reflection (stesso approccio già
     * usato per alreadyPlannedToday() in PlanDailyPostTest): il container risulta IN_PROGRESS per
     * 3 letture di fila e FINISHED alla quarta — il polling deve accorgersene senza esaurire i
     * tentativi e senza attendere realmente (Sleep::fake()).
     */
    public function test_waits_for_the_container_to_become_ready_before_giving_up(): void
    {
        Sleep::fake();

        Http::fake([
            'graph.instagram.com/*' => Http::sequence()
                ->push(['status_code' => 'IN_PROGRESS'])
                ->push(['status_code' => 'IN_PROGRESS'])
                ->push(['status_code' => 'IN_PROGRESS'])
                ->push(['status_code' => 'FINISHED']),
        ]);

        $method = new ReflectionMethod(PublishInstagramPostJob::class, 'waitUntilReady');
        $method->setAccessible(true);

        $job = new PublishInstagramPostJob(0);
        $method->invoke($job, app(InstagramGraphService::class), 'CONTAINER1', 'token-di-test');

        // 3 letture IN_PROGRESS -> 3 sleep da 1 minuto prima della quarta lettura (FINISHED).
        Sleep::assertSequence([
            Sleep::for(60)->seconds(),
            Sleep::for(60)->seconds(),
            Sleep::for(60)->seconds(),
        ]);
    }

    public function test_gives_up_after_five_minutes_if_the_container_never_becomes_ready(): void
    {
        Sleep::fake();

        Http::fake([
            'graph.instagram.com/*' => Http::response(['status_code' => 'IN_PROGRESS']),
        ]);

        $method = new ReflectionMethod(PublishInstagramPostJob::class, 'waitUntilReady');
        $method->setAccessible(true);

        $job = new PublishInstagramPostJob(0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('non pronto dopo 5 tentativi');

        $method->invoke($job, app(InstagramGraphService::class), 'CONTAINER1', 'token-di-test');
    }

    public function test_stops_immediately_on_container_error_without_waiting_the_full_timeout(): void
    {
        Sleep::fake();

        Http::fake([
            'graph.instagram.com/*' => Http::response(['status_code' => 'ERROR']),
        ]);

        $method = new ReflectionMethod(PublishInstagramPostJob::class, 'waitUntilReady');
        $method->setAccessible(true);

        $job = new PublishInstagramPostJob(0);

        try {
            $method->invoke($job, app(InstagramGraphService::class), 'CONTAINER1', 'token-di-test');
            $this->fail('Doveva lanciare RuntimeException.');
        } catch (\ReflectionException|\RuntimeException $e) {
            // ok
        }

        Sleep::assertNeverSlept();
    }

    /**
     * End-to-end (dispatchSync, come nel percorso reale di pubblicazione): un container che parte
     * IN_PROGRESS e diventa FINISHED al secondo giro deve comunque risultare pubblicato, non
     * fallito — copre esattamente lo scenario di Post#50, ora corretto dal polling più paziente.
     */
    public function test_publishes_successfully_once_the_container_becomes_ready(): void
    {
        Sleep::fake();
        $post = $this->makeConnectedPost();

        Http::fake([
            '*/content_publishing_limit*' => Http::response(['data' => [['quota_usage' => 1, 'config' => ['quota_total' => 100]]]]),
            '*/media_publish' => Http::response(['id' => 'PUBLISHED1']),
            '*/CONTAINER1*' => Http::sequence()
                ->push(['status_code' => 'IN_PROGRESS'])
                ->push(['status_code' => 'FINISHED']),
            '*/media' => Http::response(['id' => 'CONTAINER1']),
        ]);

        PublishInstagramPostJob::dispatchSync($post->id);

        $post->refresh();
        $this->assertSame('published', $post->status);
        $this->assertSame('PUBLISHED1', $post->platform_post_id);
    }

    /**
     * Fix 1 (coda vera): se il job esaurisce i tentativi, deve fallire in modo visibile — sia sul
     * Post (status 'failed') sia nel log ERROR dedicato, cablato nel canale 'alerts' (fix 4) così
     * da non restare annegato nel resto di laravel.log.
     */
    public function test_a_permanently_failing_job_marks_the_post_failed_and_logs_visibly(): void
    {
        $post = $this->makeConnectedPost();

        Log::spy();

        $job = new PublishInstagramPostJob($post->id);
        $job->failed(new \RuntimeException('Pubblicazione fallita: errore simulato'));

        $this->assertSame('failed', $post->fresh()->status);
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message) => str_contains($message, (string) $post->id) && str_contains($message, 'errore simulato'))
            ->once();
    }
}
