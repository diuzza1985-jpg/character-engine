<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotTopic extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id', 'title', 'summary', 'source_label', 'source_url',
        'status', 'discovered_at', 'used_at',
    ];

    protected function casts(): array
    {
        return [
            'discovered_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function character()
    {
        return $this->belongsTo(Character::class);
    }
}
