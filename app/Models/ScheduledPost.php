<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id',
        'weekly_schedule_slot_id',
        'post_type',
        'mode',
        'life_event_id',
        'instructions',
        'scheduled_at',
        'status',
        'post_id',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function weeklyScheduleSlot()
    {
        return $this->belongsTo(WeeklyScheduleSlot::class);
    }

    public function lifeEvent()
    {
        return $this->belongsTo(LifeEvent::class);
    }

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
