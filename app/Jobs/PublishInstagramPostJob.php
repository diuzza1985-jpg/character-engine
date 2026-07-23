<?php
namespace App\Jobs;
use App\Models\Post;
use App\Services\InstagramGraphService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;
class PublishInstagramPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 5;
    public $backoff = [30, 60, 300, 900, 3600];
    public function __construct(private int $postId) {}
    public function handle(InstagramGraphService $instagram): void
    {
        $post = Post::with('character.socialAccount')->findOrFail($this->postId);
        $account = $post->character->socialAccount;
        if (! $account || $account->status !== 'connected') {
            $post->update(['status' => 'failed']);
            Log::warning("PublishInstagramPostJob: account Instagram non connesso per post {$post->id}");
            return;
        }
        $limit = $instagram->getPublishingLimit($account->ig_user_id, $account->access_token);
        $usage = $limit['data'][0]['quota_usage'] ?? 0;
        $cap = $limit['data'][0]['config']['quota_total'] ?? 100;
        if ($usage >= $cap) {
            Log::warning("PublishInstagramPostJob: quota esaurita ({$usage}/{$cap}) per account {$account->ig_user_id}, rimando post {$post->id} di 1h");
            $this->release(3600);
            return;
        }
        $containerId = match ($post->media_type) {
            'carousel' => $this->buildCarouselContainer($instagram, $post, $account),
            'story' => $this->buildStoryContainer($instagram, $post, $account),
            default => $this->buildSingleImageContainer($instagram, $post, $account),
        };
        $this->waitUntilReady($instagram, $containerId, $account->access_token);
        $published = $instagram->publishContainer($account->ig_user_id, $account->access_token, $containerId);
        $post->update([
            'status' => 'published',
            'platform' => 'instagram',
            'platform_post_id' => $published['id'] ?? null,
            'published_at' => now(),
        ]);
        Log::info("PublishInstagramPostJob: post {$post->id} pubblicato, platform_post_id={$published['id']}");
    }
    private function buildSingleImageContainer(InstagramGraphService $instagram, Post $post, $account): string
    {
        $mediaUrl = URL::temporarySignedRoute('media.show', now()->addDay(), ['post' => $post->id]);
        Log::info("PublishInstagramPostJob: creo container singolo per post {$post->id}", ['media_url' => $mediaUrl]);
        $container = $instagram->createMediaContainer($account->ig_user_id, $account->access_token, $mediaUrl, $post->caption ?? '');
        if (empty($container['id'])) {
            throw new RuntimeException('Creazione media container fallita: ' . json_encode($container));
        }
        return $container['id'];
    }
    private function buildCarouselContainer(InstagramGraphService $instagram, Post $post, $account): string
    {
        $childrenIds = [];
        foreach ($post->media_urls as $index => $path) {
            $mediaUrl = URL::temporarySignedRoute('media.show', now()->addDay(), ['post' => $post->id, 'index' => $index]);
            Log::info("PublishInstagramPostJob: creo carousel child {$index} per post {$post->id}", ['media_url' => $mediaUrl]);
            $child = $instagram->createCarouselChildContainer($account->ig_user_id, $account->access_token, $mediaUrl);
            if (empty($child['id'])) {
                throw new RuntimeException("Creazione carousel child container fallita (slide {$index}): " . json_encode($child));
            }
            $this->waitUntilReady($instagram, $child['id'], $account->access_token);
            $childrenIds[] = $child['id'];
        }
        if (count($childrenIds) < 2) {
            throw new RuntimeException('Un carosello richiede almeno 2 slide pronte, trovate: ' . count($childrenIds));
        }
        $parent = $instagram->createCarouselContainer($account->ig_user_id, $account->access_token, $childrenIds, $post->caption ?? '');
        if (empty($parent['id'])) {
            throw new RuntimeException('Creazione carousel container padre fallita: ' . json_encode($parent));
        }
        return $parent['id'];
    }
    private function buildStoryContainer(InstagramGraphService $instagram, Post $post, $account): string
    {
        $mediaUrl = URL::temporarySignedRoute('media.show', now()->addDay(), ['post' => $post->id]);
        Log::info("PublishInstagramPostJob: creo story container per post {$post->id}", ['media_url' => $mediaUrl]);
        $container = $instagram->createStoryContainer($account->ig_user_id, $account->access_token, $mediaUrl);
        if (empty($container['id'])) {
            throw new RuntimeException('Creazione story container fallita: ' . json_encode($container));
        }
        return $container['id'];
    }
    private function waitUntilReady(InstagramGraphService $instagram, string $containerId, string $accessToken): void
    {
        $maxAttempts = 6;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $status = $instagram->getContainerStatus($containerId, $accessToken);
            if ($status === 'FINISHED') {
                return;
            }
            if (in_array($status, ['ERROR', 'EXPIRED'], true)) {
                throw new RuntimeException("Container {$containerId} in stato {$status}");
            }
            sleep(5);
        }
        throw new RuntimeException("Container {$containerId} non pronto dopo {$maxAttempts} tentativi (stato ancora IN_PROGRESS)");
    }
    public function failed(Throwable $exception): void
    {
        Post::where('id', $this->postId)->update(['status' => 'failed']);
        Log::error("PublishInstagramPostJob fallito per post {$this->postId}: " . $exception->getMessage());
    }
}
