<?php
namespace App\Console\Commands;
use App\Jobs\GenerateNewsStoryJob;
use App\Models\Character;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
class TestGenerateStory extends Command
{
    protected $signature = 'test:generate-story {character=sofia}';
    protected $description = 'Genera una story di commento a una notizia recente, riusando una foto già pubblicata (nessuna chiamata a fal.ai)';
    public function handle(): int
    {
        $character = Character::where('slug', $this->argument('character'))->first();
        if (! $character) {
            $this->error('Personaggio non trovato.');
            return self::FAILURE;
        }
        GenerateNewsStoryJob::dispatchSync($character->id);
        $post = Post::where('character_id', $character->id)->where('media_type', 'story')->latest()->first();
        $this->info("Post ID: {$post->id}");
        $this->line('Immagine: ' . Storage::disk('local')->path($post->media_urls[0]));
        return self::SUCCESS;
    }
}
