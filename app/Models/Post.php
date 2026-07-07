<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'character_id', 'generation_id', 'platform', 'platform_post_id',
        'scheduled_at', 'published_at', 'status', 'caption', 'media_urls',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'media_urls' => 'array',
        ];
    }

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function generation()
    {
        return $this->belongsTo(Generation::class);
    }
}
