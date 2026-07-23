<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\SceneElement;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportLegacySceneElements extends Command
{
    protected $signature = 'import:legacy-scene-elements {--attach-character=} {--source=scene-library}';

    protected $description = 'Importa la libreria condivisa di location/props/mood/lighting/camera/activity dal vecchio prototipo PHP (file già copiati in storage/app/legacy-import/{source})';

    private const FOLDER_CATEGORIES = [
        'location' => 'locations',
        'prop' => 'props',
    ];

    private const FILE_CATEGORIES = [
        'mood' => 'mood',
        'lighting' => 'lighting',
        'camera' => 'camera',
        'activity' => 'activities',
    ];

    public function handle(): int
    {
        $source = $this->option('source');
        $basePath = storage_path("app/legacy-import/{$source}");

        if (! is_dir($basePath)) {
            $this->error("Cartella non trovata: {$basePath}. Copia prima i file (vedi passo 1).");
            return self::FAILURE;
        }

        $character = null;
        if ($slug = $this->option('attach-character')) {
            $character = Character::where('slug', $slug)->first();
            if (! $character) {
                $this->error("Personaggio con slug '{$slug}' non trovato.");
                return self::FAILURE;
            }
        }

        $total = 0;

        foreach (self::FOLDER_CATEGORIES as $category => $folderName) {
            $dir = "{$basePath}/{$folderName}";
            if (! is_dir($dir)) {
                continue;
            }

            foreach (glob("{$dir}/*", GLOB_ONLYDIR) as $elementDir) {
                $key = basename($elementDir);

                [$name, $description] = $this->readPart($elementDir, 'description');
                [, $rules] = $this->readPart($elementDir, 'rules');
                [, $objects] = $this->readPart($elementDir, 'objects');
                [, $camera] = $this->readPart($elementDir, 'camera');
                [, $lighting] = $this->readPart($elementDir, 'lighting');

                $rulesText = trim(implode("\n\n", array_filter([$rules, $objects])));

                $element = SceneElement::updateOrCreate(
                    ['tenant_id' => null, 'category' => $category, 'key' => $key],
                    [
                        'name' => $name ?: Str::headline($key),
                        'description' => $description,
                        'rules_text' => $rulesText ?: null,
                        'camera_notes' => $camera,
                        'lighting_notes' => $lighting,
                    ]
                );

                $this->attachIfNeeded($character, $element);
                $total++;
            }
        }

        foreach (self::FILE_CATEGORIES as $category => $folderName) {
            $dir = "{$basePath}/{$folderName}";
            if (! is_dir($dir)) {
                continue;
            }

            foreach (glob("{$dir}/*.md") as $file) {
                $key = pathinfo($file, PATHINFO_FILENAME);
                [$name, $body] = $this->splitHeading(trim(file_get_contents($file)));

                $element = SceneElement::updateOrCreate(
                    ['tenant_id' => null, 'category' => $category, 'key' => $key],
                    [
                        'name' => $name ?: Str::headline($key),
                        'description' => $body,
                    ]
                );

                $this->attachIfNeeded($character, $element);
                $total++;
            }
        }

        $this->info("Elementi di scena importati/aggiornati: {$total}");

        if ($character) {
            $this->info("Collegati al personaggio: {$character->name}");
        } else {
            $this->info('Nessun personaggio collegato automaticamente (usa --attach-character=slug per farlo).');
        }

        return self::SUCCESS;
    }

    private function readPart(string $dir, string $filename): array
    {
        $path = "{$dir}/{$filename}.md";

        if (! is_file($path)) {
            return [null, null];
        }

        return $this->splitHeading(trim(file_get_contents($path)));
    }

    private function splitHeading(string $content): array
    {
        if ($content === '') {
            return [null, null];
        }

        $parts = preg_split('/\r?\n/', $content, 2);
        $first = trim($parts[0]);

        if (str_starts_with($first, '#')) {
            $heading = trim(ltrim($first, '#'));
            $body = isset($parts[1]) ? trim($parts[1]) : '';
            return [$heading !== '' ? $heading : null, $body !== '' ? $body : null];
        }

        return [null, $content];
    }

    private function attachIfNeeded(?Character $character, SceneElement $element): void
    {
        if (! $character) {
            return;
        }

        if (! $character->sceneElements()->where('scene_element_id', $element->id)->exists()) {
            $character->sceneElements()->attach($element->id);
        }
    }
}
