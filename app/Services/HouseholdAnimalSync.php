<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterKinship;
use App\Models\CharacterRelationship;
use Illuminate\Support\Collection;

/**
 * Un animale collegato a un personaggio (Character.kind = 'animale', Fase 2 mondo narrativo) è
 * conosciuto anche dal resto del suo nucleo familiare stretto (genitori/figli diretti,
 * CharacterKinship in entrambe le direzioni di registrazione) — non solo da chi lo ha dichiarato
 * per primo.
 *
 * Due punti di innesco distinti:
 * - ConvertCharacterDraftToCharacter::syncWorldCharacters(), quando un animale viene
 *   aggiunto/aggiornato dal questionario (propagateAnimal, un animale alla volta).
 * - CharacterDetail::addKinship(), quando una parentela viene aggiunta DOPO che uno o più animali
 *   erano già stati dichiarati da una delle due parti — senza questo secondo aggancio la
 *   propagazione scattava solo risalvando il questionario (limite reale segnalato dall'utente,
 *   corretto qui con syncAllForCharacter).
 */
class HouseholdAnimalSync
{
    /**
     * Genitori e figli diretti di $character secondo CharacterKinship, indipendentemente da quale
     * dei due lati ha registrato la parentela (dalla pagina del figlio con figlio/figlia, o dalla
     * pagina del genitore con genitore) — un solo passo di famiglia, non l'intero albero.
     */
    public function householdMembersOf(Character $character): Collection
    {
        $ids = CharacterKinship::where('character_id', $character->id)
            ->whereIn('relationship_type', ['figlio', 'figlia', 'genitore'])
            ->pluck('related_character_id')
            ->merge(
                CharacterKinship::where('related_character_id', $character->id)
                    ->whereIn('relationship_type', ['figlio', 'figlia', 'genitore'])
                    ->pluck('character_id')
            )
            ->unique();

        return Character::whereIn('id', $ids)->get();
    }

    /**
     * Propaga un singolo animale dal personaggio che lo possiede ($owner) a tutto il suo nucleo
     * familiare attuale — usato subito dopo aver creato/aggiornato l'animale stesso.
     */
    public function propagateAnimal(Character $owner, Character $animal, string $name, ?string $type): void
    {
        foreach ($this->householdMembersOf($owner) as $member) {
            CharacterRelationship::updateOrCreate(
                ['character_id' => $member->id, 'related_character_id' => $animal->id],
                ['name' => $name, 'type' => $type]
            );
        }
    }

    /**
     * Sincronizza TUTTI gli animali già noti a $character con tutto il suo nucleo familiare
     * attuale, in entrambe le direzioni — usato quando cambia la composizione della famiglia
     * (nuova parentela aggiunta), non solo quando cambia l'elenco degli animali.
     */
    public function syncAllForCharacter(Character $character): void
    {
        $household = $this->householdMembersOf($character);
        if ($household->isEmpty()) {
            return;
        }

        $ownAnimalRelationships = CharacterRelationship::where('character_id', $character->id)
            ->whereHas('relatedCharacter', fn ($q) => $q->where('kind', 'animale'))
            ->get();

        foreach ($household as $member) {
            foreach ($ownAnimalRelationships as $relationship) {
                CharacterRelationship::updateOrCreate(
                    ['character_id' => $member->id, 'related_character_id' => $relationship->related_character_id],
                    ['name' => $relationship->name, 'type' => $relationship->type]
                );
            }

            // Bidirezionale: gli animali già noti al membro della famiglia devono arrivare anche
            // a $character, non solo il contrario.
            $memberAnimalRelationships = CharacterRelationship::where('character_id', $member->id)
                ->whereHas('relatedCharacter', fn ($q) => $q->where('kind', 'animale'))
                ->get();

            foreach ($memberAnimalRelationships as $relationship) {
                CharacterRelationship::updateOrCreate(
                    ['character_id' => $character->id, 'related_character_id' => $relationship->related_character_id],
                    ['name' => $relationship->name, 'type' => $relationship->type]
                );
            }
        }
    }
}
