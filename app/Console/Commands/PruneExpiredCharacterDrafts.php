<?php

namespace App\Console\Commands;

use App\Models\CharacterDraft;
use Illuminate\Console\Command;

class PruneExpiredCharacterDrafts extends Command
{
    protected $signature = 'character-drafts:prune-expired';

    protected $description = 'Elimina le bozze di personaggio scadute (utenti anonimi che non hanno mai completato la registrazione)';

    public function handle(): int
    {
        $count = CharacterDraft::where('expires_at', '<', now())
            ->where('status', '!=', 'convertito')
            ->delete();

        $this->info("Bozze scadute eliminate: {$count}.");

        return self::SUCCESS;
    }
}
