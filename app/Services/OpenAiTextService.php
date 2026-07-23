<?php
namespace App\Services;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class OpenAiTextService
{
    public function generate(string $prompt): array
    {
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.text_model'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
            ])
            ->throw()
            ->json();
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (! $content) {
            throw new RuntimeException('OpenAI non ha restituito contenuto.');
        }
        $data = json_decode($content, true);
        if (! is_array($data)) {
            throw new RuntimeException('OpenAI non ha restituito un JSON valido: ' . $content);
        }
        $this->validate($data);
        return $data;
    }
    public function generateReply(string $prompt): string
    {
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.text_model'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
            ])
            ->throw()
            ->json();
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (! $content || trim($content) === '') {
            throw new RuntimeException('OpenAI non ha restituito una risposta.');
        }
        return trim($content, " \t\n\r\0\x0B\"'");
    }
    /**
     * Genera in UNA sola chiamata la risposta per più commenti insieme.
     * Restituisce l'array grezzo "replies" (ciascuno con comment_id + reply).
     */
    public function generateBatchReplies(string $prompt): array
    {
        $response = Http::withToken(config('services.openai.key'))
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.text_model'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => ['type' => 'json_object'],
            ])
            ->throw()
            ->json();
        $content = $response['choices'][0]['message']['content'] ?? null;
        if (! $content) {
            throw new RuntimeException('OpenAI non ha restituito contenuto.');
        }
        $data = json_decode($content, true);
        if (! is_array($data) || ! array_key_exists('replies', $data) || ! is_array($data['replies'])) {
            throw new RuntimeException('OpenAI non ha restituito il formato atteso per le risposte multiple: ' . $content);
        }
        return $data['replies'];
    }
    private function validate(array $data): void
    {
        foreach (['rubrica', 'titolo', 'caption', 'hashtags', 'scene', 'comments', 'replies'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw new RuntimeException("Campo mancante nella risposta OpenAI: {$key}");
            }
        }
    }
}
