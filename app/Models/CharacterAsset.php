<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterAsset extends Model
{
    use HasFactory;

    protected $fillable = ['character_id', 'type', 'label', 'file_path', 'metadata', 'is_default', 'hair_length_shown'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function character()
    {
        return $this->belongsTo(Character::class);
    }
}
