<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimelineEntry extends Model
{
    use HasFactory;

    protected $fillable = ['character_id', 'life_event_id', 'topic', 'scene', 'title'];

    protected function casts(): array
    {
        return ['scene' => 'array'];
    }

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function lifeEvent()
    {
        return $this->belongsTo(LifeEvent::class);
    }
}
