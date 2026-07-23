<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklyScheduleSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'day_of_week',
        'time_of_day',
        'post_type',
        'mode',
        'life_event_id',
        'instructions',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function lifeEvent()
    {
        return $this->belongsTo(LifeEvent::class);
    }

    public function scheduledPosts()
    {
        return $this->hasMany(ScheduledPost::class);
    }
}
