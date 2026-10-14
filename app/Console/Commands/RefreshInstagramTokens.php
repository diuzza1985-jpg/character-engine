<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Services\InstagramGraphService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Bug reale corretto il 13 settembre 2026 (Post#58/59/60 falliti): InstagramGraphService::
 * refreshLongLivedToken() esisteva già ma non era chiamato da nessuna parte nel codebase — ogni
 * token Instagram (validità 60 giorni) scadeva senza che nessuno lo rinnovasse, bloccando in
 * silenzio pubblicazione e reply-comments finché non falliva davvero un post (nessun avviso prima
 * di allora). Questo comando gira ogni giorno e rinnova solo i token in scadenza entro
 * REFRESH_WINDOW_DAYS: Meta accetta ig_refresh_token solo su un token ancora valido, quindi
 * bisogna anticipare la scadenza, non rincorrerla.
 */
class RefreshInstagramTokens extends Command
{
    protected $signature = 'instagram:refresh-tokens';

    protected $description = 'Rinnova i token Instagram long-lived in scadenza entro pochi giorni';

    private const REFRESH_WINDOW_DAYS = 7;

    public function handle(InstagramGraphService $instagram): int
    {
        $accounts = SocialAccount::where('status', 'connected')
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addDays(self::REFRESH_WINDOW_DAYS))
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('Nessun token Instagram in scadenza.');

            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            try {
                $refreshed = $instagram->refreshLongLivedToken($account->access_token);

                if (empty($refreshed['access_token']) || empty($refreshed['expires_in'])) {
                    throw new RuntimeException('Risposta refresh inattesa: ' . json_encode($refreshed));
                }

                $account->update([
                    'access_token' => $refreshed['access_token'],
                    'token_expires_at' => now()->addSeconds($refreshed['expires_in']),
                    'last_refreshed_at' => now(),
                ]);

                $this->info("Token rinnovato per account {$account->ig_user_id} (character_id={$account->character_id}), nuova scadenza {$account->token_expires_at}.");
            } catch (Throwable $e) {
                // Token già scaduto (finestra di anticipo non bastata, es. ambiente sospeso per
                // giorni, come l'11-13 settembre 2026): Meta non lo rinnova più, va rifatto l'OAuth
                // da capo (InstagramAuthController::redirect). Marcarlo 'disconnected' fa sì che
                // PublishInstagramPostJob/ReplyToInstagramComments/la UI (EditCharacter) se ne
                // accorgano subito invece di ritentare pubblicazioni al buio con un token morto.
                $account->update(['status' => 'disconnected']);
                $message = "Refresh token fallito per account {$account->ig_user_id} (character_id={$account->character_id}): {$e->getMessage()}";
                $this->error($message);
                Log::error($message);
            }
        }

        return self::SUCCESS;
    }
}
