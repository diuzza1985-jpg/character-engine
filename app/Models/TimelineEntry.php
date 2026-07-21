<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimelineEntry extends Model
{
    use HasFactory;

    protected $fillable = ['character_id', 'life_event_id', 'storyline_id', 'topic', 'scene', 'title'];

    protected function casts(): array
    {
        return ['scene' => 'array'];
    }

    public function character()
    {
        return $this->belongsTo(Character::class);
    }

    public function lifeEvent()
    {
        return $this->belongsTo(LifeEvent::class);
    }

    public function storyline()
    {
        return $this->belongsTo(Storyline::class);
    }


    protected $casts = [
        // ... cast già esistenti (probabile 'scene' => 'array') ...
        'memorability' => 'float',
        'last_referenced_at' => 'datetime',
    ];

    /**
     * Memorability effettiva: usa il valore esplicito se presente,
     * altrimenti eredita dal weight del life_event di origine, altrimenti un default neutro.
     */
    public function effectiveMemorability(): float
    {
        if ($this->memorability !== null) {
            return $this->memorability;
        }

        if ($this->lifeEvent && $this->lifeEvent->weight !== null) {
            // weight non è detto sia già in scala 0-1: normalizzare se necessario
            // in base alla scala reale usata in life_events (verificare in tinker).
            return (float) $this->lifeEvent->weight;
        }

        return 0.5;
    }

    public function markRecalled(): void
    {
        $this->increment('referenced_count');
        $this->update(['last_referenced_at' => now()]);
    }
}
