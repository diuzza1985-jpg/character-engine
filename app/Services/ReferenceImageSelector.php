<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterAsset;

class ReferenceImageSelector
{
    // Pubblica: riusata da AssetsRelationManager per sapere quali type raffigurano il
    // personaggio (a differenza di companion_*/reference_sheet/oggetti), es. per decidere quando
    // richiedere hair_length_shown sul caricamento manuale delle foto.
    public const ROTATION_TYPES = ['face', 'full_body', 'smile', 'thinking', 'write'];

    public function pick(Character $character): ?CharacterAsset
    {
        $assets = CharacterAsset::where('character_id', $character->id)
            ->whereIn('type', self::ROTATION_TYPES)
            ->get();

        if ($assets->isEmpty()) {
            return CharacterAsset::where('character_id', $character->id)->first();
        }

        $default = $assets->firstWhere('is_default', true);

        return $default ?? $assets->random();
    }
}
