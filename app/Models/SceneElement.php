<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SceneElement extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', 'category', 'key', 'name', 'description',
        'rules_text', 'camera_notes', 'lighting_notes', 'image_asset_id',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function imageAsset()
    {
        return $this->belongsTo(CharacterAsset::class, 'image_asset_id');
    }

    public function characters()
    {
        return $this->belongsToMany(Character::class, 'character_scene_elements');
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('tenant_id');
    }
}
