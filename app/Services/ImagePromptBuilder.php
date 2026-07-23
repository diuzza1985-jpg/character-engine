<?php
namespace App\Services;
use App\Models\Character;
use App\Models\SceneElement;
use App\Support\TemporalContext;
use Illuminate\Support\Facades\File;
class ImagePromptBuilder
{
    private const REFERENCE_PHOTO_OBJECTS = [
        'laptop' => 'laptop',
        'notebook' => 'notebook and pen',
        'coffee_mug' => 'coffee mug on a desk',
        'desk' => 'wooden desk and office bookshelf',
    ];

    private const SEASONAL_WARDROBE_OVERRIDE = [
        'estate' => "PRIORITARIO — è estate: maniche corte, tessuti leggeri (cotone, lino), niente maglioni/felpe/cardigan/maniche lunghe pesanti anche se menzionati sopra come possibili.",
        'inverno' => "PRIORITARIO — è inverno: maniche lunghe, maglioni/cardigan/felpe leggeri sono benvenuti, niente canotte o abiti estivi anche se menzionati sopra come possibili.",
        'primavera' => "PRIORITARIO — è primavera: strati leggeri, maniche lunghe sottili o corte, niente piumini pesanti o abiti prettamente estivi.",
        'autunno' => "PRIORITARIO — è autunno: maniche lunghe leggere, cardigan sottili benvenuti, niente canotte o maglioni pesantissimi da pieno inverno.",
    ];

    public function build(Character $character, array $scene): array
    {
        $sceneContext = $this->buildSceneContext($scene);
        $season = TemporalContext::season();
        $wardrobe = $this->wardrobeGuidance($character, $season);
        $styleGuide = $this->globalFile('style');
        $prompt = <<<TXT
{$character->name}, real photograph, {$season} in Italy.
SCENE:
{$sceneContext}
IDENTITY: keep her face, skin tone, hair and glasses identical to the reference photo. Everything else — background, pose, clothing, objects — must match the scene above, not the reference photo.
WARDROBE: {$wardrobe}
STYLE: {$styleGuide}
Realistic lifestyle photography, natural light, vertical 4:5 format, no CGI, no illustration, no beauty filter.
TXT;
        return [
            'prompt' => $prompt,
            'negative_prompt' => $this->negativePrompt($scene, $season),
            'aspect_ratio' => '4:5',
        ];
    }

    private function buildSceneContext(array $scene): string
    {
        $location = $this->findElement('location', $scene['location'] ?? '');
        $activity = $this->findElement('activity', $scene['activity'] ?? '');
        $mood = $this->findElement('mood', $scene['mood'] ?? '');
        $lighting = $this->findElement('lighting', $scene['lighting'] ?? '');
        $propElements = collect($scene['props'] ?? [])
            ->map(fn ($key) => $this->findElement('prop', $key))
            ->filter();
        $sentences = array_filter([
            $location ? "Location: {$location->name} — {$location->description}" : null,
            $activity ? "Activity: {$activity->name} — {$activity->description}" : null,
            $mood ? "Mood: {$mood->name} — {$mood->description}" : null,
            $lighting ? "Lighting: {$lighting->name} — {$lighting->description}" : null,
            $propElements->isNotEmpty()
                ? 'Objects in scene: ' . $propElements->map(fn ($p) => "{$p->name} ({$p->description})")->implode('; ')
                : null,
        ]);
        $rules = array_filter([
            $location?->rules_text,
            $location?->camera_notes,
            $location?->lighting_notes,
        ]);
        $result = implode("\n", $sentences);
        if ($rules) {
            $result .= "\n\nConsistency notes: " . implode(' ', $rules);
        }
        return $result;
    }

    private function findElement(string $category, string $key): ?SceneElement
    {
        if ($key === '') {
            return null;
        }
        return SceneElement::whereNull('tenant_id')
            ->where('category', $category)
            ->where('key', $key)
            ->first();
    }

    private function wardrobeGuidance(Character $character, string $season): string
    {
        $profile = $character->visualProfile;
        $notes = $profile
            ? trim(implode(' ', array_filter([$profile->wardrobe_notes, $profile->visual_rules_text])))
            : "Abbigliamento semplice e vario, mai identico tra una foto e l'altra.";

        $override = self::SEASONAL_WARDROBE_OVERRIDE[$season] ?? null;

        return $override ? "{$notes} {$override}" : $notes;
    }

    private function negativePrompt(array $scene, string $season): string
    {
        $negatives = [
            'AI artifacts', 'extra fingers', 'plastic skin', 'fashion pose',
            'luxury house', 'gaming setup', 'random stickers', 'different pets',
        ];
        $isHomeOffice = ($scene['location'] ?? null) === 'home_office';
        $sceneProps = $scene['props'] ?? [];
        if (! $isHomeOffice) {
            foreach (self::REFERENCE_PHOTO_OBJECTS as $propKey => $negativeLabel) {
                if (! in_array($propKey, $sceneProps, true)) {
                    $negatives[] = $negativeLabel;
                }
            }
        }
        if ($season === 'estate') {
            $negatives[] = 'heavy sweater';
            $negatives[] = 'wool jacket';
            $negatives[] = 'winter coat';
            $negatives[] = 'long sleeves';
            $negatives[] = 'turtleneck';
        } elseif ($season === 'inverno') {
            $negatives[] = 'short sleeves';
            $negatives[] = 'tank top';
            $negatives[] = 'summer dress';
        }
        return implode(', ', $negatives);
    }

    private function globalFile(string $name): string
    {
        $path = resource_path("prompts/global/{$name}.md");
        return File::exists($path) ? trim(File::get($path)) : '';
    }
}
