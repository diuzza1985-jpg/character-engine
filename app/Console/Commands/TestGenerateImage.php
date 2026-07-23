<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Services\FalImageService;
use App\Services\ImagePromptBuilder;
use App\Services\ReferenceImageSelector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class TestGenerateImage extends Command
{
    protected $signature = 'test:generate-image {slug=sofia}';

    protected $description = 'Comando temporaneo per testare la generazione immagine via fal.ai (chiamata a pagamento)';

    public function handle(
        ImagePromptBuilder $promptBuilder,
        ReferenceImageSelector $refSelector,
        FalImageService $fal
    ): int {
        $character = Character::where('slug', $this->argument('slug'))->firstOrFail();

        $scene = [
            'location' => 'local_park',
            'activity' => 'nature_walk',
            'mood' => 'tired_but_smiling',
            'lighting' => 'morning',
            'camera' => 'instagram_vertical',
            'props' => ['water_bottle', 'phone'],
        ];

        $reference = $refSelector->pick($character);
        $this->info('Reference usata: ' . $reference->type);

        $built = $promptBuilder->build($character, $scene);
        $this->info('Prompt pronto (' . strlen($built['prompt']) . ' caratteri), chiamo fal.ai...');

        $result = $fal->generate($built['prompt'], $built['negative_prompt'], $reference);

        $filename = 'test-generations/' . now()->format('Ymd_His') . '_' . $character->slug . '.png';
        Storage::disk('local')->put($filename, $result['binary']);

        $this->info('Immagine salvata: storage/app/' . $filename);
        $this->info('Seed usato: ' . $result['seed']);

        return self::SUCCESS;
    }
}
