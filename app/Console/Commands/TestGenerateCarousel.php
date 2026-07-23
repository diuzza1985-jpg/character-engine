<?php

namespace App\Console\Commands;

use App\Jobs\GenerateInstagramPostJob;
use App\Models\Character;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestGenerateCarousel extends Command
{
    protected $signature = 'test:generate-carousel {character=sofia}';
    protected $description = 'Genera un carosello completo (chiamata reale OpenAI + fal.ai) e stampa i path locali delle slide';

    public function handle(): int
    {
        $character = Character::where('slug', $this->argument('character'))->first();
        if (! $character) {
            $this->error('Personaggio non trovato.');
            return self::FAILURE;
        }

        GenerateInstagramPostJob::dispatchSync($character->id, 'carousel');

        $post = Post::where('character_id', $character->id)->latest()->first();

        $this->info("Post ID: {$post->id} — media_type: {$post->media_type}");
        $this->line("Caption:\n{$post->caption}\n");

        foreach ($post->media_urls as $i => $path) {
            $fullPath = Storage::disk('local')->path($path);
            $this->line("Slide {$i}: {$fullPath}");
        }

        return self::SUCCESS;
    }
}
