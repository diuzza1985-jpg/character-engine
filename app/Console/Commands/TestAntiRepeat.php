<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\TimelineEntry;
use App\Services\LifeEventSelector;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestAntiRepeat extends Command
{
    protected $signature = 'test:anti-repeat {character=sofia} {--days=30}';
    protected $description = 'Simula N giorni di scelte, isolato dalla cronologia reale (transazione + rollback)';

    public function handle(LifeEventSelector $selector): int
    {
        $slug = $this->argument('character');
        $days = (int) $this->option('days');

        $character = Character::where('slug', $slug)->first();
        if (! $character) {
            $this->error("Personaggio '{$slug}' non trovato.");
            return self::FAILURE;
        }

        $realNow = Carbon::now();
        $log = [];
        $violations = [];

        DB::beginTransaction();

        try {
            // Isolamento: rimuoviamo temporaneamente la cronologia reale così la
            // simulazione parte da zero. Vive solo dentro la transazione, il
            // rollback finale la ripristina esattamente com'era.
            TimelineEntry::where('character_id', $character->id)->delete();

            for ($day = 1; $day <= $days; $day++) {
                Carbon::setTestNow($realNow->copy()->subDays($days - $day));

                $event = $selector->pick($character);

                $lastSameEvent = collect($log)->last(fn ($row) => $row['life_event_id'] === $event->id);
                if ($lastSameEvent) {
                    $gapDays = $day - $lastSameEvent['day'];
                    if ($gapDays < $event->cooldown_days) {
                        $violations[] = sprintf('Giorno %d: "%s" riusato dopo %d giorni (cooldown: %d)', $day, $event->title, $gapDays, $event->cooldown_days);
                    }
                }

                TimelineEntry::create([
                    'character_id' => $character->id,
                    'life_event_id' => $event->id,
                    'topic' => $event->category,
                    'scene' => $event->scene_hint,
                    'title' => $event->title,
                ]);

                $log[] = ['day' => $day, 'life_event_id' => $event->id, 'title' => $event->title];
                $this->line(sprintf('Giorno %2d: %s', $day, $event->title));
            }
        } finally {
            Carbon::setTestNow();
            DB::rollBack();
        }

        $this->newLine();
        if (empty($violations)) {
            $this->info("Nessuna violazione su {$days} giorni simulati.");
        } else {
            $this->warn(count($violations) . " violazioni:");
            foreach ($violations as $v) {
                $this->line(" - {$v}");
            }
        }

        $this->comment("Rollback eseguito: la cronologia reale di {$slug} è intatta.");

        return self::SUCCESS;
    }
}
