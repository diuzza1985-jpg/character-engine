<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Services\LifeEventSelector;
use App\Services\OpenAiTextService;
use App\Services\PromptBuilder;
use Illuminate\Console\Command;

class TestGeneratePost extends Command
{
    protected $signature = 'test:generate-post {slug=sofia}';

    protected $description = 'Comando temporaneo per testare la generazione testo (life event + prompt + OpenAI)';

    public function handle(
        LifeEventSelector $selector,
        PromptBuilder $promptBuilder,
        OpenAiTextService $openAi
    ): int {
        $character = Character::where('slug', $this->argument('slug'))->firstOrFail();

        $lifeEvent = $selector->pick($character);
        $this->info("Life event scelto: {$lifeEvent->title} (topic: {$lifeEvent->scene_hint['topic']})");

        $prompt = $promptBuilder->buildInstagramPostPrompt($character, $lifeEvent);
        $this->info('Prompt costruito: ' . strlen($prompt) . ' caratteri.');

        $post = $openAi->generate($prompt);

        $this->newLine();
        $this->info('=== RISULTATO ===');
        $this->line('Rubrica: ' . $post['rubrica']);
        $this->line('Titolo: ' . $post['titolo']);
        $this->line('Caption: ' . $post['caption']);
        $this->line('Hashtags: ' . implode(' ', $post['hashtags']));
        $this->line('Scene: ' . json_encode($post['scene'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('Comments: ' . json_encode($post['comments'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->line('Replies: ' . json_encode($post['replies'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
