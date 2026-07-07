<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterBibleSection extends Model
{
    use HasFactory;

    protected $fillable = ['character_id', 'section_key', 'content', 'version'];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }
}
