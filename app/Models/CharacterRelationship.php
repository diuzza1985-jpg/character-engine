<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class CharacterRelationship extends Model
{
    use HasFactory;
    protected $fillable = ['character_id', 'name', 'type', 'sentiment', 'last_interaction_at', 'notes'];
    protected function casts(): array
    {
        return ['sentiment' => 'float', 'last_interaction_at' => 'datetime'];
    }
    public function character()
    {
        return $this->belongsTo(Character::class);
    }
}
