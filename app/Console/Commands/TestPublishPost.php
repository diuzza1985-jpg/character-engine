<?php

namespace App\Console\Commands;

use App\Jobs\PublishInstagramPostJob;
use App\Models\Post;
use Illuminate\Console\Command;

class TestPublishPost extends Command
{
    protected $signature = 'test:publish-post {postId}';
    protected $description = 'Pubblica su Instagram un Post esistente (dispatchSync, per test manuale)';

    public function handle(): int
    {
        $postId = (int) $this->argument('postId');
        $post = Post::find($postId);

        if (! $post) {
            $this->error("Post {$postId} non trovato.");
            return self::FAILURE;
        }

        $this->info("Pubblico post {$postId} (character_id={$post->character_id})...");

        PublishInstagramPostJob::dispatchSync($postId);

        $post->refresh();

        $this->info("Stato finale: {$post->status}");
        $this->info("platform_post_id: " . ($post->platform_post_id ?? '(nessuno)'));
        $this->info("published_at: " . ($post->published_at ?? '(nessuno)'));

        return self::SUCCESS;
    }
}
