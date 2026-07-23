<?php
namespace App\Services;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
class NewsDigestService
{
    public function fetchRecent(string $query, ?string $category = null, int $limit = 5, int $maxAgeDays = 3): array
    {
        $key = config('services.newsdata.key');
        if (! $key) {
            Log::warning('NewsDigestService: NEWSDATA_API_KEY non configurata, salto la ricerca notizie.');
            return [];
        }
        try {
            $params = [
                'apikey' => $key,
                'qInTitle' => $query,
                'language' => 'it',
                'size' => min($limit * 3, 10),
            ];
            if ($category) {
                $params['category'] = $category;
            }
            $response = Http::timeout(15)->get('https://newsdata.io/api/1/latest', $params);
            if ($response->failed()) {
                Log::warning('NewsDigestService: richiesta fallita', ['query' => $query, 'status' => $response->status(), 'body' => $response->body()]);
                return [];
            }
            $articles = $response->json('results', []);
        } catch (Throwable $e) {
            Log::warning('NewsDigestService: eccezione durante la richiesta, proseguo senza notizie', ['query' => $query, 'error' => $e->getMessage()]);
            return [];
        }
        $cutoff = now()->subDays($maxAgeDays);
        return collect($articles)
            ->map(fn ($a) => [
                'title' => $a['title'] ?? null,
                'description' => $a['description'] ?? null,
                'source' => $a['source_id'] ?? null,
                'pubDate' => $a['pubDate'] ?? null,
            ])
            ->filter(fn ($a) => filled($a['title']))
            ->filter(function ($a) use ($cutoff) {
                if (! $a['pubDate']) {
                    return true;
                }
                try {
                    return Carbon::parse($a['pubDate'])->greaterThanOrEqualTo($cutoff);
                } catch (Throwable) {
                    return true;
                }
            })
            ->take($limit)
            ->values()
            ->all();
    }
}
