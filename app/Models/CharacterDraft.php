<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterDraft extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'session_token', 'tenant_id',
        'goal', 'goal_secondary', 'target_audience', 'niche',
        'name', 'role', 'one_liner', 'living_situation', 'pets', 'environment',
        'traits', 'core_values', 'dislikes',
        'communication_formality', 'communication_verbosity', 'communication_directness', 'emoji_usage',
        'humor_level', 'joke_targets', 'humor_safe_topics', 'content_safe_limits',
        'age_range', 'presentation', 'style_archetype', 'hair_color', 'hair_style',
        'eye_color', 'body_type', 'nose_detail', 'mouth_detail', 'distinguishing_detail',
        'status', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'target_audience' => 'array',
            'niche' => 'array',
            'pets' => 'array',
            'traits' => 'array',
            'core_values' => 'array',
            'dislikes' => 'array',
            'joke_targets' => 'array',
            'humor_safe_topics' => 'array',
            'content_safe_limits' => 'array',
            'communication_formality' => 'integer',
            'communication_verbosity' => 'integer',
            'communication_directness' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
