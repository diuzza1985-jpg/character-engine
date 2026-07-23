<?php

namespace App\Console\Commands;

use App\Models\Generation;
use App\Models\LifeEvent;
use App\Models\TimelineEntry;
use Illuminate\Console\Command;

class InspectGeneration extends Command
{
    protected $signature = 'inspect:generation {id}';

    protected $description = 'Mostra i dettagli salvati di una generazione: scena usata per il testo/immagine vs life event suggerito';

    public function handle(): int
    {
        $generation = Generation::findOrFail($this->argument('id'));
        $output = $generation->output;

        $this->info('Caption: ' . ($output['post']['caption'] ?? 'n/d'));
        $this->newLine();
        $this->info('Scene usata per generare testo e immagine (post.scene):');
        $this->line(json_encode($output['post']['scene'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (! empty($output['timeline_entry_id'])) {
            $entry = TimelineEntry::find($output['timeline_entry_id']);
            $lifeEvent = $entry ? LifeEvent::find($entry->life_event_id) : null;

            $this->newLine();
            $this->info('Life event suggerito come "momento di oggi": ' . ($lifeEvent->title ?? 'n/d'));
            $this->line(json_encode($lifeEvent->scene_hint ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
