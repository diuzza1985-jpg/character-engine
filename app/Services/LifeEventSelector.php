<?php
namespace App\Services;
use App\Models\Character;
use App\Models\LifeEvent;
use App\Models\TimelineEntry;
class LifeEventSelector
{
    public function pick(Character $character, ?array &$debug = null): LifeEvent
    {
        $events = LifeEvent::where(function ($query) use ($character) {
            $query->where('character_id', $character->id)
                ->orWhereNull('character_id');
        })->get();

        if ($events->isEmpty()) {
            throw new \RuntimeException("Nessun life_event configurato per {$character->name}.");
        }
        $recentEntries = TimelineEntry::where('character_id', $character->id)
            ->whereNotNull('life_event_id')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $lastId = $this->lastUsedId($recentEntries);

        $candidates = $this->excludeLastUsed($events, $recentEntries);
        $afterExcludeLast = $candidates->pluck('id')->all();

        $candidates = $this->excludeInCooldown($candidates, $recentEntries);
        $afterCooldown = $candidates->pluck('id')->all();

        $usedFallback = false;
        if ($candidates->isEmpty()) {
            $usedFallback = true;
            $candidates = $this->fallbackPreferringStalest($events, $recentEntries, $lastId);
        }

        $picked = $this->weightedPick($candidates);

        if ($debug !== null) {
            $debug = [
                'recent_count' => $recentEntries->count(),
                'last_id' => $lastId,
                'after_exclude_last' => $afterExcludeLast,
                'after_cooldown' => $afterCooldown,
                'used_fallback' => $usedFallback,
                'final_candidates' => $candidates->pluck('id')->all(),
                'picked_id' => $picked->id,
            ];
        }

        return $picked;
    }

    private function lastUsedId($recentEntries): ?int
    {
        $id = optional($recentEntries->first())->life_event_id;
        return $id !== null ? (int) $id : null;
    }

    private function excludeLastUsed($events, $recentEntries)
    {
        $lastId = $this->lastUsedId($recentEntries);
        return $events->reject(fn ($event) => (int) $event->id === $lastId);
    }

    private function excludeInCooldown($events, $recentEntries)
    {
        return $events->filter(function ($event) use ($recentEntries) {
            if (! $event->cooldown_days) {
                return true;
            }
            $lastUse = $recentEntries->first(fn ($entry) => (int) $entry->life_event_id === (int) $event->id);
            if (! $lastUse) {
                return true;
            }
            return $lastUse->created_at->diffInDays(now()) >= $event->cooldown_days;
        });
    }

    private function fallbackPreferringStalest($events, $recentEntries, ?int $lastId)
    {
        $withoutLast = $events->reject(fn ($event) => (int) $event->id === $lastId);

        if ($withoutLast->isEmpty()) {
            return $events;
        }

        $withoutLast->each(function ($event) use ($recentEntries) {
            $lastUse = $recentEntries->first(fn ($entry) => (int) $entry->life_event_id === (int) $event->id);
            $event->setAttribute('_last_used_at', $lastUse?->created_at?->timestamp ?? 0);
        });

        $oldestFirst = $withoutLast->sortBy('_last_used_at')->values();

        $staleTierSize = max(1, (int) ceil($oldestFirst->count() / 2));

        return $oldestFirst->take($staleTierSize)->values();
    }

    private function weightedPick($events)
    {
        $totalWeight = $events->sum(fn ($event) => max(1, $event->weight ?? 50));
        $random = random_int(1, max(1, $totalWeight));
        foreach ($events as $event) {
            $weight = max(1, $event->weight ?? 50);
            if ($random <= $weight) {
                return $event;
            }
            $random -= $weight;
        }
        return $events->random();
    }
}
