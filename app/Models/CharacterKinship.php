<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CharacterKinship extends Model
{
    use HasFactory;

    /**
     * Elenco fisso di tipi di parentela, validato lato app (nessun vincolo enum a livello DB,
     * stesso principio già in uso altrove nel progetto per narrative_format/formato).
     */
    public const RELATIONSHIP_TYPES = [
        'marito', 'moglie', 'compagno_a', 'figlio', 'figlia',
        'genitore', 'fratello', 'sorella', 'amico_a', 'collega', 'altro',
    ];

    /**
     * Etichetta invertita da mostrare sul personaggio collegato (es. Fernando vede "moglie" se
     * Sofia lo ha segnato come "marito") — best-effort, non grammaticalmente perfetta in ogni
     * caso (es. "collega"/"altro" restano invariati, non hanno un inverso naturale diverso).
     */
    public const INVERSE_LABELS = [
        'marito' => 'moglie',
        'moglie' => 'marito',
        'figlio' => 'genitore',
        'figlia' => 'genitore',
        'genitore' => 'figlio_a',
        'fratello' => 'fratello_a',
        'sorella' => 'fratello_a',
    ];

    protected $fillable = ['character_id', 'related_character_id', 'relationship_type', 'notes'];

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function relatedCharacter()
    {
        return $this->belongsTo(Character::class, 'related_character_id');
    }

    public function inverseLabel(): string
    {
        return self::INVERSE_LABELS[$this->relationship_type] ?? $this->relationship_type;
    }
}
