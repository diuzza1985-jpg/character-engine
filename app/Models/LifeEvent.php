<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LifeEvent extends Model
{
    use HasFactory;

    protected $fillable = ['character_id', 'category', 'title', 'weight', 'cooldown_days', 'scene_hint'];

    protected function casts(): array
    {
        return ['scene_hint' => 'array'];
    }

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function timelineEntries()
    {
        return $this->hasMany(TimelineEntry::class);
    }
}
