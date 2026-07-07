<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterVisualProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id', 'age_description', 'face_description',
        'hair_description', 'wardrobe_notes', 'visual_rules_text',
    ];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }
}
