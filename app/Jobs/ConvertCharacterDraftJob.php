<?php

namespace App\Jobs;

use App\Models\CharacterDraft;
use App\Services\ConvertCharacterDraftToCharacter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConvertCharacterDraftJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public function __construct(private string $draftId, private int $tenantId) {}

    public function handle(ConvertCharacterDraftToCharacter $converter): void
    {
        $draft = CharacterDraft::findOrFail($this->draftId);
        $converter->convert($draft, $this->tenantId);
    }

    public function failed(Throwable $exception): void
    {
        Log::error("ConvertCharacterDraftJob fallito per draft {$this->draftId}: " . $exception->getMessage());
    }
}
