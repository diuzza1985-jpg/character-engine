<?php
namespace App\Services;
use App\Models\CharacterAsset;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
class FalImageService
{
    public function generate(string $prompt, string $negativePrompt, CharacterAsset $reference): array
    {
        $dataUri = $this->toDataUri($reference);
        $payload = [
            'prompt' => $prompt,
            'reference_image_urls' => [$dataUri],
            'negative_prompt' => $negativePrompt,
            'image_size' => 'portrait_4_3',
            'rendering_speed' => 'QUALITY',
            'style' => 'REALISTIC',
            'expand_prompt' => false,
            'seed' => random_int(1, 999999999),
        ];
        Log::info('fal.ai request', [
            'prompt' => $prompt,
            'prompt_length' => strlen($prompt),
            'negative_prompt' => $negativePrompt,
            'reference_image_url_prefix' => substr($dataUri, 0, 60),
            'reference_image_url_length' => strlen($dataUri),
            'other_params' => collect($payload)->except(['prompt', 'reference_image_urls'])->all(),
        ]);
        $response = Http::withHeaders([
                'Authorization' => 'Key ' . config('services.fal.key'),
            ])
            ->timeout(180)
            ->post('https://fal.run/fal-ai/ideogram/character', $payload)
            ->throw()
            ->json();
        Log::info('fal.ai response', $response);
        $imageUrl = $response['images'][0]['url'] ?? null;
        if (! $imageUrl) {
            throw new RuntimeException('fal.ai non ha restituito un URL immagine. Risposta: ' . json_encode($response));
        }
        $imageBinary = Http::timeout(60)->get($imageUrl)->throw()->body();
        return [
            'binary' => $imageBinary,
            'seed' => $response['seed'] ?? $payload['seed'],
            'source_url' => $imageUrl,
        ];
    }
    private function toDataUri(CharacterAsset $asset): string
    {
        $contents = Storage::disk('local')->get($asset->file_path);
        $mime = Storage::disk('local')->mimeType($asset->file_path) ?? 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($contents);
    }

    /**
     * Per contenuti dove il personaggio non deve comparire (formato "oggetto", Fase 2): generate()
     * usa fal-ai/ideogram/character, pensato apposta per PRESERVARE il personaggio a partire da una
     * reference — sbagliato per questo caso, non solo per il tipo non-nullable. Qui uso un modello
     * text-to-image generico, senza reference_image_urls.
     *
     * 'fal-ai/flux/dev' (FLUX.1 [dev], generalista, text-to-image puro): a differenza di
     * fal-ai/flux-pro/kontext (già presente in config/services.php ma per image-editing con
     * immagine di input, non adatto qui), non richiede alcuna immagine di partenza.
     */
    public function generateStandalone(string $prompt, string $negativePrompt): array
    {
        $payload = [
            'prompt' => $prompt,
            'negative_prompt' => $negativePrompt,
            'image_size' => 'portrait_4_3',
            'num_inference_steps' => 28,
            'seed' => random_int(1, 999999999),
        ];

        Log::info('fal.ai request (standalone, no reference)', [
            'prompt' => $prompt,
            'prompt_length' => strlen($prompt),
            'negative_prompt' => $negativePrompt,
        ]);

        $response = Http::withHeaders([
                'Authorization' => 'Key ' . config('services.fal.key'),
            ])
            ->timeout(180)
            ->post('https://fal.run/fal-ai/flux/dev', $payload)
            ->throw()
            ->json();

        Log::info('fal.ai response (standalone)', $response);

        $imageUrl = $response['images'][0]['url'] ?? null;
        if (! $imageUrl) {
            throw new RuntimeException('fal.ai non ha restituito un URL immagine. Risposta: ' . json_encode($response));
        }

        $imageBinary = Http::timeout(60)->get($imageUrl)->throw()->body();

        return [
            'binary' => $imageBinary,
            'seed' => $response['seed'] ?? $payload['seed'],
            'source_url' => $imageUrl,
        ];
    }
}
