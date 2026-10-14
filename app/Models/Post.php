<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $fillable = ['character_id', 'generation_id', 'related_post_id', 'platform', 'media_type', 'narrative_format', 'platform_post_id', 'scheduled_at', 'published_at', 'status', 'caption', 'media_urls', 'comic_target', 'comic_target_checked_at'];

    protected function casts(): array
    {
        return [
            'media_urls' => 'array',
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'comic_target_checked_at' => 'datetime',
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

    /**
     * Il post originale (image/carousel/...) di cui questo post "story" è la condivisione
     * automatica — null per ogni post che non è una condivisione (la stragrande maggioranza).
     */
    public function relatedPost()
    {
        return $this->belongsTo(Post::class, 'related_post_id');
    }

    /**
     * Inverso: la story condivisa automaticamente A PARTIRE da questo post, se esiste già —
     * usato per l'idempotenza in PublishInstagramPostJob (mai condividere due volte lo stesso post).
     */
    public function storyShare()
    {
        return $this->hasOne(Post::class, 'related_post_id');
    }
}
