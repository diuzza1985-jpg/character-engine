<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Accesso OAuth "app-only" (client_credentials) a Reddit, sola lettura di contenuti pubblici —
 * nessuna azione utente (post/commento/voto), solo liste "hot"/"rising" per
 * character:check-hot-topics (documento temi-attualità). Piano gratuito Reddit (100 richieste/
 * minuto): uso personale/non commerciale, confermato dall'utente prima di questa implementazione
 * — da rivedere se il prodotto diventa commerciale (vendita crediti/abbonamenti attiva).
 */
class RedditService
{
    private function token(): string
    {
        return Cache::remember('reddit_oauth_token', 3300, function () {
            $clientId = config('services.reddit.client_id');
            $clientSecret = config('services.reddit.client_secret');
            if (! $clientId || ! $clientSecret) {
                throw new RuntimeException('Credenziali Reddit non configurate (REDDIT_CLIENT_ID/REDDIT_CLIENT_SECRET).');
            }

            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->withHeaders(['User-Agent' => config('services.reddit.user_agent')])
                ->post('https://www.reddit.com/api/v1/access_token', [
                    'grant_type' => 'client_credentials',
                ])
                ->throw()
                ->json();

            $token = $response['access_token'] ?? null;
            if (! $token) {
                throw new RuntimeException('Reddit non ha restituito un access token.');
            }

            return $token;
        });
    }

    /**
     * @return array<int, array{title:string, score:int, num_comments:int, created_utc:int, permalink:string, selftext:string, subreddit:string}>
     */
    public function fetchListing(string $subreddit, string $listing = 'hot', int $limit = 15): array
    {
        $response = Http::withToken($this->token())
            ->withHeaders(['User-Agent' => config('services.reddit.user_agent')])
            ->timeout(20)
            ->get("https://oauth.reddit.com/r/{$subreddit}/{$listing}", ['limit' => $limit])
            ->throw()
            ->json();

        $children = $response['data']['children'] ?? [];

        return collect($children)
            ->map(fn (array $child) => $child['data'] ?? [])
            // "stickied" = post fissato dai moderatori (regole, annunci): non è mai un segnale
            // reale di "argomento del momento", va escluso a prescindere dal punteggio.
            ->filter(fn (array $post) => ! empty($post['title']) && empty($post['stickied']))
            ->map(fn (array $post) => [
                'title' => $post['title'],
                'score' => (int) ($post['score'] ?? 0),
                'num_comments' => (int) ($post['num_comments'] ?? 0),
                'created_utc' => (int) ($post['created_utc'] ?? 0),
                'permalink' => 'https://reddit.com'.($post['permalink'] ?? ''),
                'selftext' => mb_substr((string) ($post['selftext'] ?? ''), 0, 300),
                'subreddit' => $post['subreddit'] ?? $subreddit,
            ])
            ->values()
            ->all();
    }
}
