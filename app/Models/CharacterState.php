<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class CharacterState extends Model
{
    use HasFactory;
    protected $table = 'character_state';
    protected $fillable = [
        'character_id', 'mood_energia', 'mood_valenza', 'mood_stress',
        'baseline_energia', 'baseline_valenza', 'baseline_stress',
        'drives', 'last_tick_at',
    ];
    protected function casts(): array
    {
        return [
            'mood_energia' => 'float', 'mood_valenza' => 'float', 'mood_stress' => 'float',
            'baseline_energia' => 'float', 'baseline_valenza' => 'float', 'baseline_stress' => 'float',
            'drives' => 'array', 'last_tick_at' => 'datetime',
        ];
    }
    public function character()
    {
        return $this->belongsTo(Character::class);
    }
}
