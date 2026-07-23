<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Services\ImagePromptBuilder;
use App\Services\ReferenceImageSelector;
use Illuminate\Console\Command;

class TestImagePrompt extends Command
{
    protected $signature = 'test:image-prompt {slug=sofia}';

    protected $description = 'Comando temporaneo per testare ImagePromptBuilder + scelta reference';

    public function handle(ImagePromptBuilder $builder, ReferenceImageSelector $refSelector): int
    {
        $character = Character::where('slug', $this->argument('slug'))->firstOrFail();

        $scene = [
            'location' => 'lake',
            'activity' => 'nature_walk',
            'mood' => 'reflective',
            'lighting' => 'golden_hour',
            'camera' => 'instagram_vertical',
            'props' => ['backpack', 'water_bottle'],
        ];

        $result = $builder->build($character, $scene);
        $reference = $refSelector->pick($character);

        $this->info('Reference scelta: ' . ($reference?->type ?? 'NESSUNA'));
        $this->info('Prompt costruito: ' . strlen($result['prompt']) . ' caratteri.');
        $this->newLine();
        $this->line($result['prompt']);

        return self::SUCCESS;
    }
}
