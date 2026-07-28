<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * YouTube Data API v3, videos.list(chart=mostPopular): pool CONDIVISO di video in tendenza, poche
 * chiamate per ciclo di character:check-hot-topics indipendenti dal numero di personaggi — 1
 * unità per chiamata (quota gratuita 10.000/giorno), non le 100 unità di search.list. Il match
 * contro le parole chiave di ciascun personaggio avviene dopo, in memoria (CheckHotTopics), non
 * con una ricerca dedicata per personaggio (che esaurirebbe la quota in ~100 ricerche/giorno
 * totali scalando con più personaggi).
 */
class YoutubeTrendingService
{
    // Poche categorie sensate invece di scansionare tutte quelle YouTube: abbastanza varietà per
    // coprire la maggior parte delle nicchie senza moltiplicare le chiamate. ID ufficiali
    // (YouTube Data API, videoCategories.list): null = "Most Popular" generale senza filtro.
    private const CATEGORIES = [null, 28, 24, 20, 26];

    /**
     * @return array<int, array{video_id:string, title:string, description:string, channel_title:string, view_count:int, url:string}>
     */
    public function fetchTrendingPool(string $regionCode = 'IT', int $limitPerCategory = 15): array
    {
        $key = config('services.youtube.key');
        if (! $key) {
            throw new RuntimeException('Chiave YouTube non configurata (YOUTUBE_API_KEY).');
        }

        $pool = [];
        foreach (self::CATEGORIES as $categoryId) {
            try {
                $params = [
                    'part' => 'snippet,statistics',
                    'chart' => 'mostPopular',
                    'regionCode' => $regionCode,
                    'maxResults' => $limitPerCategory,
                    'key' => $key,
                ];
                if ($categoryId !== null) {
                    $params['videoCategoryId'] = $categoryId;
                }

                $response = Http::timeout(15)->get('https://www.googleapis.com/youtube/v3/videos', $params);

                if ($response->failed()) {
                    Log::warning('YoutubeTrendingService: richiesta fallita', [
                        'category' => $categoryId, 'status' => $response->status(), 'body' => $response->body(),
                    ]);
                    continue;
                }

                foreach ($response->json('items', []) as $item) {
                    $videoId = $item['id'] ?? null;
                    if (! $videoId) {
                        continue;
                    }
                    $pool[] = [
                        'video_id' => $videoId,
                        'title' => $item['snippet']['title'] ?? '',
                        'description' => mb_substr((string) ($item['snippet']['description'] ?? ''), 0, 300),
                        'channel_title' => $item['snippet']['channelTitle'] ?? '',
                        'view_count' => (int) ($item['statistics']['viewCount'] ?? 0),
                        'url' => "https://www.youtube.com/watch?v={$videoId}",
                    ];
                }
            } catch (Throwable $e) {
                Log::warning('YoutubeTrendingService: eccezione durante la richiesta', [
                    'category' => $categoryId, 'error' => $e->getMessage(),
                ]);
            }
        }

        // Un video può comparire in più categorie (es. "Most Popular" generale + una categoria
        // specifica): dedup per video_id, non serve valutarlo due volte.
        return collect($pool)
            ->unique('video_id')
            ->filter(fn (array $v) => $v['title'] !== '')
            ->values()
            ->all();
    }
}
