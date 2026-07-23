<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\CharacterAsset;
use App\Models\CharacterBibleSection;
use App\Models\CharacterVisualProfile;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportLegacyCharacter extends Command
{
    protected $signature = 'import:legacy-character {slug} {--tenant=} {--name=}';

    protected $description = 'Importa bible, profilo visivo e asset di un personaggio dal vecchio prototipo PHP (file già copiati in storage/app/legacy-import/{slug})';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $basePath = storage_path("app/legacy-import/{$slug}");

        if (! is_dir($basePath)) {
            $this->error("Cartella non trovata: {$basePath}. Copia prima i file (vedi passo 2).");
            return self::FAILURE;
        }

        $tenant = $this->option('tenant')
            ? Tenant::find($this->option('tenant'))
            : Tenant::first();

        if (! $tenant) {
            $this->error('Nessun tenant trovato. Creane uno dal pannello admin, oppure passa --tenant=ID.');
            return self::FAILURE;
        }

        $character = Character::firstOrCreate(
            ['slug' => $slug],
            [
                'tenant_id' => $tenant->id,
                'name' => $this->option('name') ?? Str::headline($slug),
                'status' => 'active',
            ]
        );

        $this->info("Personaggio: {$character->name} (id {$character->id}, tenant {$tenant->name})");

        // 1) Sezioni bible (file numerati 00_xxx.md ... 16_xxx.md)
        $bibleDir = "{$basePath}/bible";
        $bibleCount = 0;

        if (is_dir($bibleDir)) {
            foreach (glob("{$bibleDir}/*.md") as $file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                $sectionKey = preg_replace('/^\d+_/', '', $filename);
                $content = trim(file_get_contents($file));

                if ($content === '') {
                    continue;
                }

                CharacterBibleSection::updateOrCreate(
                    ['character_id' => $character->id, 'section_key' => $sectionKey],
                    ['content' => $content]
                );
                $bibleCount++;
            }
        }

        $this->info("Sezioni bible importate: {$bibleCount}");

        // 2) Profilo visivo
        $visualDir = "{$basePath}/visual";
        $read = fn (string $name) => is_file("{$visualDir}/{$name}.md")
            ? trim(file_get_contents("{$visualDir}/{$name}.md"))
            : null;

        if (is_dir($visualDir)) {
            $faceParts = array_filter([$read('appearance'), $read('description')]);

            CharacterVisualProfile::updateOrCreate(
                ['character_id' => $character->id],
                [
                    'face_description' => $faceParts ? implode("\n\n", $faceParts) : null,
                    'wardrobe_notes' => $read('wardrobe'),
                    'visual_rules_text' => $read('visual_rules'),
                ]
            );
            $this->info('Profilo visivo importato (età e capelli restano vuoti: il vecchio sistema non li separava, li puoi compilare a mano dal pannello).');
        }

        // 3) Asset immagine
        $assetsDir = "{$basePath}/assets";
        $assetCount = 0;

        if (is_dir($assetsDir)) {
            $metadata = is_file("{$assetsDir}/asset.json")
                ? json_decode(file_get_contents("{$assetsDir}/asset.json"), true)
                : [];
            $defaultType = $metadata['default'] ?? null;

            foreach (glob("{$assetsDir}/*.png") as $file) {
                $type = pathinfo($file, PATHINFO_FILENAME);
                $directory = "characters/{$tenant->id}/{$character->id}";
                $storedName = $type.'_'.Str::random(8).'.png';

                Storage::disk('local')->makeDirectory($directory);
                Storage::disk('local')->put("{$directory}/{$storedName}", file_get_contents($file));

                CharacterAsset::updateOrCreate(
                    ['character_id' => $character->id, 'type' => $type],
                    [
                        'file_path' => "{$directory}/{$storedName}",
                        'is_default' => $type === $defaultType,
                    ]
                );
                $assetCount++;
            }
        }

        $this->info("Asset immagine importati: {$assetCount}");
        $this->info('Fatto. Puoi rilanciare questo comando in sicurezza: aggiorna i dati esistenti invece di duplicarli.');

        return self::SUCCESS;
    }
}
