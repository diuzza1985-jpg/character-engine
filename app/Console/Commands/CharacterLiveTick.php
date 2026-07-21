<?php
namespace App\Console\Commands;
use App\Events\LifeEventOccurred;
use App\Models\Character;
use App\Models\CharacterRelationship;
use App\Models\CharacterState;
use App\Models\LifeEvent;
use App\Models\Storyline;
use App\Models\TimelineEntry;
use App\Services\LifeEventSelector;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use RuntimeException;

class CharacterLiveTick extends Command
{
    protected $signature = 'character:live-tick {character? : Slug o ID del personaggio (default: tutti gli attivi)}';
    protected $description = 'Tick giornaliero del motore di vita: decadimento mood, pressione/abbandono storyline, evento casuale, drives (sez. 11.2-11.6)';

    public function handle(LifeEventSelector $lifeEventSelector): int
    {
        $characters = $this->resolveCharacters();
        if ($characters->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($characters as $character) {
            $this->info("Tick per {$character->name} (#{$character->id})...");
            $this->tick($character, $lifeEventSelector);
        }

        return self::SUCCESS;
    }

    private function resolveCharacters(): Collection
    {
        $arg = $this->argument('character');
        if ($arg === null) {
            return Character::where('status', 'active')->get();
        }

        $character = ctype_digit((string) $arg)
            ? Character::find((int) $arg)
            : Character::where('slug', $arg)->first();

        if (! $character) {
            $this->error("Personaggio \"{$arg}\" non trovato.");
            return collect();
        }

        return collect([$character]);
    }

    private function tick(Character $character, LifeEventSelector $lifeEventSelector): void
    {
        $state = $this->decayMood($character);
        $this->recalculateStorylinePressure($character);
        $this->rollLifeEvent($character, $state, $lifeEventSelector);
        $this->updateDrives($character, $state);

        $state->update(['last_tick_at' => now()]);
    }

    /**
     * 11.2 — molla verso la baseline: energia = energia + (baseline - energia) * decay_rate.
     */
    private function decayMood(Character $character): CharacterState
    {
        // I default di colonna (0.6/0/0.3) valgono lato MySQL ma non sono visibili sul model appena
        // creato senza un refresh: li ripetiamo qui esplicitamente per evitare che la formula sotto
        // legga null/0.0 e lo riscriva sopra il default reale al primo save().
        $state = CharacterState::firstOrCreate(['character_id' => $character->id], [
            'mood_energia' => 0.6, 'mood_valenza' => 0, 'mood_stress' => 0.3,
            'baseline_energia' => 0.6, 'baseline_valenza' => 0, 'baseline_stress' => 0.3,
        ]);
        $decayRate = config('life_engine.mood_decay_rate');

        $state->mood_energia += ($state->baseline_energia - $state->mood_energia) * $decayRate;
        $state->mood_valenza += ($state->baseline_valenza - $state->mood_valenza) * $decayRate;
        $state->mood_stress += ($state->baseline_stress - $state->mood_stress) * $decayRate;
        $state->save();

        return $state;
    }

    /**
     * 11.3 — pressione = importanza * min(1, giorni_da_ultimo_aggiornamento / window); non persistita,
     * qui si applica solo l'effetto collaterale (auto-abbandono oltre soglia).
     */
    private function recalculateStorylinePressure(Character $character): void
    {
        $windowDays = config('life_engine.storyline_pressure_window_days');
        $abandonDays = config('life_engine.storyline_abandon_days');

        $openStorylines = $character->storylines()->where('status', 'aperta')->get();

        foreach ($openStorylines as $storyline) {
            $daysSinceUpdate = $storyline->daysSinceLastUpdate();
            $pressure = $storyline->pressure($windowDays);

            if ($daysSinceUpdate !== null && $daysSinceUpdate > $abandonDays) {
                $storyline->update(['status' => 'abbandonata']);
                $this->line("  storyline #{$storyline->id} \"{$storyline->title}\" abbandonata (ferma da {$daysSinceUpdate}gg).");
                continue;
            }

            $this->line("  storyline #{$storyline->id} \"{$storyline->title}\": pressione " . round($pressure, 2) . ".");
        }
    }

    /**
     * 11.4 — probabilità di "oggi succede qualcosa" crescente con i giorni di inattività, poi pesca
     * dal pool life_events rispettando weight/cooldown_days (già gestito da LifeEventSelector).
     */
    private function rollLifeEvent(Character $character, CharacterState $state, LifeEventSelector $lifeEventSelector): void
    {
        $probability = $this->eventProbability($character);

        if (! $this->rollSucceeds($probability)) {
            $this->line('  nessun evento oggi (probabilità ' . round($probability, 2) . ').');
            return;
        }

        try {
            $lifeEvent = $lifeEventSelector->pick($character);
        } catch (RuntimeException $e) {
            $this->warn("  {$e->getMessage()}");
            return;
        }

        $this->applyMoodDeltas($state, $lifeEvent->mood_deltas ?? []);

        $storyline = null;
        if ($lifeEvent->can_start_storyline) {
            $storyline = Storyline::create([
                'character_id' => $character->id,
                'title' => $lifeEvent->title,
                'status' => 'aperta',
                'importance' => 0.5,
                'started_at' => now(),
                'last_updated_at' => now(),
            ]);
        }

        if ($lifeEvent->target_relationship) {
            $this->touchRelationship($character, $lifeEvent);
        }

        $timelineEntry = TimelineEntry::create([
            'character_id' => $character->id,
            'life_event_id' => $lifeEvent->id,
            'storyline_id' => $storyline?->id,
            'topic' => $lifeEvent->scene_hint['topic'] ?? null,
            'title' => $lifeEvent->title,
            // 'scene' resta vuoto qui di proposito: la descrizione in linguaggio naturale è un
            // TODO, arriverà da una chiamata GPT batch notturna (vedi LifeEventOccurred sotto).

            // --- nuovo, Fase 1 motore di memoria ---
            'memorability' => $lifeEvent->weight !== null
                ? min(1.0, $lifeEvent->weight / config('life_engine.memory.life_event_weight_scale'))
                : null,
        ]);

        LifeEventOccurred::dispatch($timelineEntry);

        $this->info("  evento: \"{$lifeEvent->title}\"" . ($storyline ? ' (storyline aperta)' : '') . '.');
    }

    private function eventProbability(Character $character): float
    {
        $base = config('life_engine.event_base_probability');
        $stallWindow = config('life_engine.event_stall_window_days');

        $lastEvent = TimelineEntry::where('character_id', $character->id)
            ->whereNotNull('life_event_id')
            ->latest('created_at')
            ->first();

        if (! $lastEvent) {
            return 1.0;
        }

        $daysSince = $lastEvent->created_at->diffInDays(now());
        return $base + (1 - $base) * min(1, $daysSince / $stallWindow);
    }

    private function rollSucceeds(float $probability): bool
    {
        return (random_int(0, 999999) / 1000000) < $probability;
    }

    private function applyMoodDeltas(CharacterState $state, array $deltas): void
    {
        if (empty($deltas)) {
            return;
        }

        $state->mood_energia = $this->clamp($state->mood_energia + ($deltas['energia'] ?? 0), 0, 1);
        $state->mood_valenza = $this->clamp($state->mood_valenza + ($deltas['valenza'] ?? 0), -1, 1);
        $state->mood_stress = $this->clamp($state->mood_stress + ($deltas['stress'] ?? 0), 0, 1);
        $state->save();
    }

    private function touchRelationship(Character $character, LifeEvent $lifeEvent): void
    {
        $relationship = CharacterRelationship::firstOrCreate(
            ['character_id' => $character->id, 'name' => $lifeEvent->target_relationship],
            ['sentiment' => 0]
        );

        $delta = $lifeEvent->mood_deltas['relationship_sentiment'] ?? 0.1;
        $relationship->update([
            'sentiment' => $this->clamp($relationship->sentiment + $delta, -1, 1),
            'last_interaction_at' => now(),
        ]);
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * 11.6 — puro conteggio SQL, nessuna chiamata GPT: per ogni category del pool life_events del
     * personaggio, quante volte è stata vissuta di recente e da quanti giorni non capita più.
     * Nota: sono conteggi grezzi per category (es. "dog_walk", "client_work"), non i drive semantici
     * ("make_people_smile" ecc.) descritti nel documento — non esiste ancora una tassonomia per quello.
     */
    private function updateDrives(Character $character, CharacterState $state): void
    {
        $lookbackDays = config('life_engine.drives_lookback_days');
        $cutoff = now()->subDays($lookbackDays);

        $categories = LifeEvent::where(function ($query) use ($character) {
            $query->where('character_id', $character->id)->orWhereNull('character_id');
        })->pluck('category')->filter()->unique()->values();

        $drives = [];
        foreach ($categories as $category) {
            $baseQuery = TimelineEntry::where('character_id', $character->id)
                ->whereHas('lifeEvent', fn ($q) => $q->where('category', $category));

            $recentCount = (clone $baseQuery)->where('created_at', '>=', $cutoff)->count();
            $lastUsedAt = (clone $baseQuery)->latest('created_at')->first()?->created_at;

            $drives[$category] = [
                'recent_count' => $recentCount,
                'days_since_last' => $lastUsedAt ? (int) $lastUsedAt->diffInDays(now()) : null,
            ];
        }

        $state->update(['drives' => $drives]);
    }
}
