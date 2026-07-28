<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterEditorialSettings extends Model
{
    use HasFactory;
    protected $table = 'character_editorial_settings';
    protected $fillable = [
        'character_id', 'max_giorni_silenzio', 'diario_ogni_n_post',
        'goal', 'goal_secondary', 'target_audience', 'niche', 'hot_topic_subreddits',
    ];
    protected function casts(): array
    {
        return [
            'max_giorni_silenzio' => 'integer',
            'diario_ogni_n_post' => 'integer',
            'target_audience' => 'array',
            'niche' => 'array',
            'hot_topic_subreddits' => 'array',
        ];
    }
    public function character()
    {
        return $this->belongsTo(Character::class);
    }
}
