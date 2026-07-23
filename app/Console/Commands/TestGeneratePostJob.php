<?php

namespace App\Console\Commands;

use App\Jobs\GenerateInstagramPostJob;
use App\Models\Character;
use App\Models\Generation;
use Illuminate\Console\Command;

class TestGeneratePostJob extends Command
{
    protected $signature = 'test:generate-post-job {slug=sofia}';

    protected $description = 'Esegue il Job completo di generazione post (testo + immagine, chiamate a pagamento)';

    public function handle(): int
    {
        $character = Character::where('slug', $this->argument('slug'))->firstOrFail();

        GenerateInstagramPostJob::dispatchSync($character->id);

        $generation = Generation::where('character_id', $character->id)->latest()->first();

        $this->info('Stato: ' . $generation->status);

        if ($generation->status === 'failed') {
            $this->error($generation->output['error'] ?? 'Errore sconosciuto');
            return self::FAILURE;
        }

        $this->line('Caption: ' . $generation->output['post']['caption']);
        $this->line('Immagine: storage/app/' . $generation->output['image_path']);
        $this->line('Post creato: id ' . $generation->output['post_id']);

        return self::SUCCESS;
    }
}
