<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class CharacterRelationship extends Model
{
    use HasFactory;
    protected $fillable = ['character_id', 'related_character_id', 'name', 'type', 'sentiment', 'last_interaction_at', 'notes'];
    protected function casts(): array
    {
        return ['sentiment' => 'float', 'last_interaction_at' => 'datetime'];
    }
    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * Null per la stragrande maggioranza delle relazioni (restano puramente narrative/testuali,
     * name/type/sentiment senza un Character dietro). Quando valorizzata, il personaggio
     * collegato è un Character normale — niente di speciale nel suo schema, può essere in
     * status draft senza social_account (mai pubblicato da solo) o attivo come chiunque altro.
     */
    public function relatedCharacter()
    {
        return $this->belongsTo(Character::class, 'related_character_id');
    }
}
