<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\Generation;
use App\Models\Post;
use App\Services\ImageTextOverlayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RecomposeCarousel extends Command
{
    protected $signature = 'test:recompose-carousel {character=sofia}';
    protected $description = 'Ricompone le slide del carosello più recente riusando la stessa immagine base, senza nuove chiamate API a pagamento';

    public function handle(ImageTextOverlayService $overlay): int
    {
        $character = Character::where('slug', $this->argument('character'))->first();
        if (! $character) {
            $this->error('Personaggio non trovato.');
            return self::FAILURE;
        }

        $generation = Generation::where('character_id', $character->id)
            ->where('purpose', 'instagram_post')
            ->whereJsonContains('input->post_type', 'carousel')
            ->latest()
            ->first();

        if (! $generation) {
            $this->error('Nessuna generazione carousel trovata per questo personaggio.');
            return self::FAILURE;
        }

        $output = $generation->output ?? [];
        $baseImagePath = $output['image_path'] ?? null;
        $slideTexts = $output['post']['carousel_slides'] ?? [];

        if (! $baseImagePath || empty($slideTexts)) {
            $this->error('Dati mancanti nella generazione (image_path o carousel_slides assenti).');
            return self::FAILURE;
        }

        if (! Storage::disk('local')->exists($baseImagePath)) {
            $this->error("Immagine base non trovata su disco: {$baseImagePath}");
            return self::FAILURE;
        }

        $this->line("Riuso immagine base: {$baseImagePath}");
        $baseBinary = Storage::disk('local')->get($baseImagePath);

        $newPaths = [];
        foreach ($slideTexts as $index => $text) {
            $composited = $overlay->overlay($baseBinary, $text);
            $slidePath = "generations/{$character->tenant_id}/{$character->id}/" . now()->format('Ymd_His') . '_' . Str::random(6) . "_slide{$index}.png";
            Storage::disk('local')->put($slidePath, $composited);
            $newPaths[] = $slidePath;
            $this->info("Slide {$index}: " . Storage::disk('local')->path($slidePath));
        }

        $postId = $output['post_id'] ?? null;
        if ($postId && ($post = Post::find($postId))) {
            $post->media_urls = $newPaths;
            $post->save();
            $this->info("Post ID {$post->id} aggiornato con le nuove slide ricomposte.");
        } else {
            $this->warn('Post collegato non trovato: le slide sono su disco ma il post non è stato aggiornato.');
        }

        $this->newLine();
        $this->comment('Nessuna chiamata a OpenAI o fal.ai effettuata.');

        return self::SUCCESS;
    }
}
