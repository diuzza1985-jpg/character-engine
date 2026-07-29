<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetUploadConsent extends Model
{
    protected $fillable = ['character_asset_id', 'user_id', 'consent_text_version', 'accepted_at'];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }

    public function asset()
    {
        return $this->belongsTo(CharacterAsset::class, 'character_asset_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
