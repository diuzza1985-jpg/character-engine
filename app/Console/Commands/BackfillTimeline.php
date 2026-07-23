<?php
namespace App\Console\Commands;
use App\Models\Character;
use App\Models\LifeEvent;
use App\Models\TimelineEntry;
use Illuminate\Console\Command;
class BackfillTimeline extends Command
{
    protected $signature = 'backfill:timeline {character=sofia}';
    protected $description = "Inserisce nella cronologia reale gli eventi già pubblicati manualmente, per allineare l'anti-ripetizione";
    public function handle(): int
    {
        $character = Character::where('slug', $this->argument('character'))->first();
        if (! $character) { $this->error('Personaggio non trovato.'); return self::FAILURE; }
        $mapping = [
            9 => 'Giornata di debug',
            8 => "Test con l'IA",
            7 => 'Passeggiata nella natura',
            6 => 'Progetto con Fernando',
            5 => 'Serata in famiglia',
            4 => 'Giornata con un cliente',
            3 => 'Visita in libreria',
            2 => 'Passeggiata con Pippo',
            1 => 'Aperitivo in centro',
            0 => 'Pomeriggio di shopping',
        ];
        foreach ($mapping as $daysAgo => $title) {
            $event = LifeEvent::where(function ($q) use ($character) {
                $q->where('character_id', $character->id)->orWhereNull('character_id');
            })->where('title', $title)->first();
            if (! $event) { $this->warn("Life event '{$title}' non trovato, salto."); continue; }
            $exists = TimelineEntry::where('character_id', $character->id)
                ->where('life_event_id', $event->id)
                ->whereDate('created_at', now()->subDays($daysAgo)->toDateString())
                ->exists();
            if ($exists) { $this->line("Già presente: {$title} ({$daysAgo}gg fa), salto."); continue; }
            $entry = TimelineEntry::create([
                'character_id' => $character->id, 'life_event_id' => $event->id,
                'topic' => $event->category, 'scene' => $event->scene_hint, 'title' => $event->title,
            ]);
            $entry->created_at = now()->subDays($daysAgo);
            $entry->updated_at = now()->subDays($daysAgo);
            $entry->save();
            $this->info("Inserito: {$title} ({$daysAgo}gg fa)");
        }
        return self::SUCCESS;
    }
}
