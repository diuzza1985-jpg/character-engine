<?php

namespace Tests\Feature;

use App\Models\CharacterDraft;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PruneExpiredCharacterDraftsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_deletes_only_expired_non_converted_drafts(): void
    {
        $expiredInCorso = CharacterDraft::create(['session_token' => 'a', 'status' => 'in_corso', 'expires_at' => now()->subDay()]);
        $expiredCompletato = CharacterDraft::create(['session_token' => 'b', 'status' => 'completato', 'expires_at' => now()->subDay()]);
        $expiredConvertito = CharacterDraft::create(['session_token' => 'c', 'status' => 'convertito', 'expires_at' => now()->subDay()]);
        $notExpired = CharacterDraft::create(['session_token' => 'd', 'status' => 'in_corso', 'expires_at' => now()->addDay()]);
        $noExpiry = CharacterDraft::create(['session_token' => 'e', 'status' => 'in_corso', 'expires_at' => null]);

        $this->artisan('character-drafts:prune-expired')->assertSuccessful();

        $this->assertNull(CharacterDraft::find($expiredInCorso->id));
        $this->assertNull(CharacterDraft::find($expiredCompletato->id));
        $this->assertNotNull(CharacterDraft::find($expiredConvertito->id), 'una bozza convertita non va mai cancellata, anche se scaduta');
        $this->assertNotNull(CharacterDraft::find($notExpired->id));
        $this->assertNotNull(CharacterDraft::find($noExpiry->id));
    }
}
