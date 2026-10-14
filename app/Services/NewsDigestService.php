<?php
namespace App\Services;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
class NewsDigestService
{
    // Guardia di rischio per il digest generico (bug reale osservato in test: category=top senza
    // filtri ha restituito tra l'altro un caso di stalking e la morte di un 27enne) — stesso
    // principio di CheckHotTopics::RISK_CATEGORIES (lì con un giudizio LLM dedicato, qui un filtro
    // locale per parola chiave: il digest è materiale facoltativo "di passaggio", non giustifica
    // una chiamata GPT di giudizio dedicata come un vero HotTopic). Non esaustivo per
    // costruzione: è un filtro di buon senso che riduce il rischio di proporre al cervello
    // editoriale una tragedia/cronaca nera come spunto "facoltativo", non una garanzia assoluta —
    // il prompt lascia comunque piena discrezionalità editoriale di ignorare qualunque notizia non
    // adatta al personaggio.
    private const RISK_KEYWORDS = [
        'morte', 'morto', 'morta', 'ucciso', 'uccisa', 'uccide', 'omicidio', 'femminicidio',
        'suicidio', 'suicida', 'stupro', 'violenza sessuale', 'stalking', 'accoltellato', 'accoltellata',
        'incidente mortale', 'strage', 'attentato', 'terrorismo', 'guerra', 'arrestato', 'arrestata',
        'elezioni', 'governo cade', 'crollo', 'disastro', 'tragedia', 'lutto', 'funerale',
    ];

    /**
     * Digest generico di attualità/curiosità (richiesta utente, 13 ottobre 2026): a differenza di
     * fetchRecent() sotto (legata a una query specifica, un singolo LifeEvent), qui non c'è nessun
     * argomento da cercare — serve materiale grezzo "cosa succede oggi nel mondo", volutamente
     * largo (categoria "top", nessun qInTitle) così il cervello editoriale possa scegliere da solo
     * cosa, se mai, vale la pena riprendere, anche fuori dalla nicchia abituale del personaggio.
     *
     * @return array<int, array{title: ?string, description: ?string, source: ?string, pubDate: ?string}>
     */
    public function fetchGeneralDigest(int $limit = 6, int $maxAgeDays = 1): array
    {
        $key = config('services.newsdata.key');
        if (! $key) {
            Log::warning('NewsDigestService: NEWSDATA_API_KEY non configurata, salto il digest generico.');
            return [];
        }
        try {
            $response = Http::timeout(15)->get('https://newsdata.io/api/1/latest', [
                'apikey' => $key,
                'language' => 'it',
                'country' => 'it',
                'category' => 'top',
                'size' => min($limit * 2, 10),
            ]);
            if ($response->failed()) {
                Log::warning('NewsDigestService: digest generico fallito', ['status' => $response->status(), 'body' => $response->body()]);
                return [];
            }
            $articles = $response->json('results', []);
        } catch (Throwable $e) {
            Log::warning('NewsDigestService: eccezione nel digest generico, proseguo senza', ['error' => $e->getMessage()]);
            return [];
        }

        $filtered = $this->filterAndFormat($articles, $limit * 2, $maxAgeDays);

        // Il filtro per parola chiave sopra (matchesRiskKeyword) è un primo taglio grezzo, non
        // sufficiente da solo (verificato: un caso di stalking è passato perché il testo diceva
        // "perseguita", non "stalking"; una notizia di infiltrazioni mafiose non conteneva
        // nessuna delle parole in lista) — a differenza di fetchRecent() sotto, qui le notizie
        // NON sono pre-curate da una query/categoria scelta per un LifeEvent specifico, quindi il
        // rischio di contenuto inadatto (cronaca nera, politica divisiva) è reale e non
        // enumerabile in una lista fissa. Stesso principio del giudizio LLM di
        // CheckHotTopics::judge() (RISK_CATEGORIES), qui più semplice perché il digest è materiale
        // facoltativo, non un HotTopic dedicato: un'unica chiamata classifica tutte le notizie
        // superstiti insieme (economico, cache giornaliera a monte in EditorialContextBuilder).
        return array_slice($this->filterRiskyWithLlm($filtered), 0, $limit);
    }

    /**
     * Fail-closed per scelta (ritorna [] se il giudizio stesso fallisce): per questo digest
     * generico è più sicuro mostrare nessuna notizia oggi che rischiare di proporre come spunto
     * facoltativo una notizia di cronaca nera/tragedia/politica divisiva non filtrata.
     */
    private function filterRiskyWithLlm(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        $key = config('services.openai.key');
        if (! $key) {
            return [];
        }

        try {
            $elenco = collect($items)
                ->map(fn ($a, $i) => ($i + 1) . ". {$a['title']}" . ($a['description'] ? " — {$a['description']}" : ''))
                ->implode("\n");

            $prompt = <<<TXT
Classifica ciascuna di queste notizie per rischio editoriale: verranno proposte come spunto facoltativo a un personaggio social dal tono leggero, familiare, mai legato a cronaca nera/tragedie/politica divisiva.

{$elenco}

Per ciascuna, nello stesso ordine, restituisci true in "sicura" SOLO se è un argomento leggero, neutro o curioso adatto a quel contesto; false se riguarda anche solo in parte morte, violenza, crimine, criminalità organizzata, tragedia, lutto, politica elettorale/divisiva, o qualunque tema sensibile — anche se la connessione non è nel titolo ma nella descrizione.
TXT;

            $response = Http::withToken($key)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.text_model'),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'filtro_rischio_notizie',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['sicure'],
                                'properties' => [
                                    'sicure' => ['type' => 'array', 'items' => ['type' => 'boolean']],
                                ],
                            ],
                        ],
                    ],
                ])
                ->throw()
                ->json();

            $content = $response['choices'][0]['message']['content'] ?? null;
            $data = $content ? json_decode($content, true) : null;
            $flags = is_array($data) ? ($data['sicure'] ?? []) : null;

            if (! is_array($flags) || count($flags) !== count($items)) {
                Log::warning('NewsDigestService: filtro di rischio LLM con forma inattesa, scarto il digest per prudenza', ['response' => $content]);
                return [];
            }

            return collect($items)->filter(fn ($a, $i) => $flags[$i] ?? false)->values()->all();
        } catch (Throwable $e) {
            Log::warning('NewsDigestService: filtro di rischio LLM fallito, scarto il digest per prudenza', ['error' => $e->getMessage()]);
            return [];
        }
    }

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
        return $this->filterAndFormat($articles, $limit, $maxAgeDays);
    }

    private function filterAndFormat(array $articles, int $limit, int $maxAgeDays): array
    {
        $cutoff = now()->subDays($maxAgeDays);
        return collect($articles)
            ->map(fn ($a) => [
                'title' => $a['title'] ?? null,
                'description' => $a['description'] ?? null,
                'source' => $a['source_id'] ?? null,
                'pubDate' => $a['pubDate'] ?? null,
            ])
            ->filter(fn ($a) => filled($a['title']))
            ->filter(fn ($a) => ! $this->matchesRiskKeyword($a['title'] . ' ' . ($a['description'] ?? '')))
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

    private function matchesRiskKeyword(string $haystack): bool
    {
        $haystack = mb_strtolower($haystack);
        foreach (self::RISK_KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }
        return false;
    }
}
