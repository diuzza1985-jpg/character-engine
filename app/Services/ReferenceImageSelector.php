<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterAsset;

class ReferenceImageSelector
{
    private const ROTATION_TYPES = ['face', 'full_body', 'smile', 'thinking', 'write'];

    public function pick(Character $character): ?CharacterAsset
    {
        $assets = CharacterAsset::where('character_id', $character->id)
            ->whereIn('type', self::ROTATION_TYPES)
            ->get();

        if ($assets->isEmpty()) {
            return CharacterAsset::where('character_id', $character->id)->first();
        }

        return $assets->random();
    }
}
