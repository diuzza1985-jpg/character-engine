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
            'output_format' => 'png',
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

    /**
     * Fase 3 mondo narrativo: genera una scena in cui il personaggio principale compare insieme a
     * una persona/animale del suo mondo (related_character_id valorizzato + asset disponibile).
     * generate() sopra usa fal-ai/ideogram/character, che usa solo la PRIMA reference passata —
     * inadatto qui, servono entrambi i soggetti riconoscibili. fal-ai/nano-banana-pro/edit accetta
     * più reference in image_urls e compone davvero più soggetti: verificato con una chiamata
     * reale (due reference distinte → un'immagine con entrambe le persone visibili).
     *
     * A differenza di generate(), questo endpoint non espone un parametro negative_prompt
     * separato (schema reale verificato via openapi.json della queue fal): le esclusioni vanno
     * scritte nel prompt stesso.
     */
    public function generateWithMultipleSubjects(string $prompt, string $negativePrompt, CharacterAsset $mainReference, CharacterAsset $secondaryReference): array
    {
        $payload = [
            'prompt' => "{$prompt}\n\nEvita assolutamente: {$negativePrompt}.",
            'image_urls' => [$this->toDataUri($mainReference), $this->toDataUri($secondaryReference)],
            'num_images' => 1,
            'seed' => random_int(1, 999999999),
        ];

        Log::info('fal.ai request (multi-subject)', [
            'prompt' => $prompt,
            'prompt_length' => strlen($prompt),
            'negative_prompt' => $negativePrompt,
            'other_params' => collect($payload)->except(['prompt', 'image_urls'])->all(),
        ]);

        $response = Http::withHeaders([
                'Authorization' => 'Key ' . config('services.fal.key'),
            ])
            ->timeout(180)
            ->post('https://fal.run/fal-ai/nano-banana-pro/edit', $payload)
            ->throw()
            ->json();

        Log::info('fal.ai response (multi-subject)', $response);

        $imageUrl = $response['images'][0]['url'] ?? null;
        if (! $imageUrl) {
            throw new RuntimeException('fal.ai (nano-banana-pro) non ha restituito un URL immagine. Risposta: ' . json_encode($response));
        }

        $imageBinary = Http::timeout(60)->get($imageUrl)->throw()->body();

        return [
            // L'endpoint non restituisce mai 'seed' in risposta (verificato con una chiamata
            // reale): il fallback al seed inviato è sempre il caso effettivo, non solo teorico.
            'binary' => $imageBinary,
            'seed' => $response['seed'] ?? $payload['seed'],
            'source_url' => $imageUrl,
        ];
    }
}
