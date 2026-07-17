<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 1:1 con Character, stesso pattern di CharacterVisualProfile. Pensata per crescere con
 * altre impostazioni editoriali per-personaggio in futuro (non solo max_giorni_silenzio).
 */
class CharacterEditorialSettings extends Model
{
    use HasFactory;
    protected $table = 'character_editorial_settings';
    protected $fillable = ['character_id', 'max_giorni_silenzio'];
    protected function casts(): array
    {
        return ['max_giorni_silenzio' => 'integer'];
    }
    public function character()
    {
        return $this->belongsTo(Character::class);
    }
}
