<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\HotTopic;
use App\Services\YoutubeTrendingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Formato "tema_caldo" (documento temi-attualità, sez. 3): un personaggio reagisce a un tema di
 * tendenza pertinente alla sua nicchia. Fonte attiva: YouTube Data API v3 (videos.list,
 * chart=mostPopular) — Reddit resta dormiente (RedditService, commit e290b7c: sia l'endpoint
 * pubblico anonimo sia la registrazione OAuth self-service sono bloccati da Reddit dal 2026).
 *
 * Architettura a pool condiviso, non una chiamata per personaggio: YoutubeTrendingService recupera
 * UN pool di video in tendenza per l'intero ciclo (poche chiamate, indipendenti dal numero di
 * personaggi — search.list costerebbe 100 unità a chiamata, ~100 ricerche/giorno in totale;
 * videos.list ne costa 1). Il match contro le parole chiave di ciascun personaggio avviene dopo,
 * in memoria, gratis.
 *
 * Filtro a due stadi invariato (sez. 3.C):
 *  1) grezzo — la parola chiave del personaggio compare davvero in un video del pool, nessuna
 *     chiamata LLM ancora (i video del pool sono già "mostPopular", quindi già virali per
 *     definizione: lo stadio 1 qui verifica la PERTINENZA, non la popolarità);
 *  2) giudizio LLM dedicato che decide se è davvero abbastanza rilevante/"caldo", con una guardia
 *     esplicita lato codice sulle categorie a rischio — non lasciata al solo giudizio libero del
 *     modello.
 * Se approvato, crea un HotTopic "pending": la stessa "disponibilità per il prossimo ciclo
 * editoriale" già usata per $diarioDisponibile, ma con contenuto vero (titolo/sintesi) invece di
 * un semplice booleano, quindi persistita invece che calcolata al volo.
 */
class CheckHotTopics extends Command
{
    protected $signature = 'character:check-hot-topics';

    protected $description = 'Confronta le parole chiave di ogni personaggio contro i video di tendenza YouTube e marca una disponibilità per il prossimo ciclo editoriale';

    private const MAX_CANDIDATES_PER_CHARACTER = 3;

    // Oltre questa età un tema "pending" mai scelto dal cervello editoriale scade: un tema
    // caldo di 4 giorni fa non è più caldo, non deve restare disponibile all'infinito.
    private const MAX_TOPIC_AGE_DAYS = 3;

    // Scartate SEMPRE dal codice, indipendentemente dal giudizio "rilevante" del modello (sez.
    // 3.C: "un filtro esplicito che scarti a monte... non lasciare la decisione solo al giudizio
    // libero dell'LLM").
    private const RISK_CATEGORIES = ['politica_elettorale', 'tragedia', 'cronaca_nera'];

    public function handle(YoutubeTrendingService $youtube): int
    {
        $this->expireStaleTopics();

        try {
            $pool = $youtube->fetchTrendingPool();
        } catch (Throwable $e) {
            Log::warning('CheckHotTopics: impossibile recuperare il pool di tendenze YouTube, salto questo ciclo', ['error' => $e->getMessage()]);
            $this->error("Pool YouTube non disponibile: {$e->getMessage()}");
            return self::SUCCESS;
        }

        if (empty($pool)) {
            $this->info('Pool di tendenze YouTube vuoto, nessun controllo da fare.');
            return self::SUCCESS;
        }

        $characters = Character::whereHas('editorialSettings', function ($q) {
            $q->whereNotNull('hot_topic_keywords');
        })->get();

        foreach ($characters as $character) {
            $this->processCharacter($character, $pool);
        }

        return self::SUCCESS;
    }

    private function expireStaleTopics(): void
    {
        HotTopic::where('status', 'pending')
            ->where('discovered_at', '<', now()->subDays(self::MAX_TOPIC_AGE_DAYS))
            ->update(['status' => 'expired']);
    }

    private function processCharacter(Character $character, array $pool): void
    {
        // Un tema alla volta (stesso principio del diario centellinato): se ce n'è già uno
        // pending non ancora usato, non se ne cerca un altro finché non viene consumato o scade —
        // evita di accumulare temi "caldi" ormai stantii in coda.
        if (HotTopic::where('character_id', $character->id)->where('status', 'pending')->exists()) {
            return;
        }

        $keywords = $character->editorialSettings?->hot_topic_keywords ?? [];

        if (! empty($keywords)) {
            $nicheCandidates = collect($pool)
                ->filter(function (array $video) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        if ($this->matchesKeyword($video, $keyword)) {
                            return true;
                        }
                    }
                    return false;
                })
                ->unique('video_id')
                ->sortByDesc('view_count')
                ->take(self::MAX_CANDIDATES_PER_CHARACTER)
                ->values()
                ->all();

            if (! empty($nicheCandidates) && $this->tryCreateFromCandidates($character, $nicheCandidates, inNiche: true)) {
                return;
            }
        }

        // Traccia generalista (richiesta utente, 13 ottobre 2026: "cercare anche tra i temi di
        // attualità diversi dalla comfort zone... ma con forte appeal del pubblico"): prima la
        // traccia sopra filtrava SOLO per corrispondenza alla nicchia stretta del personaggio
        // (sez. judge() sotto, criterio "rilevante"), quindi quando — come per la maggior parte
        // dei cicli osservati — nessun video del pool nomina esplicitamente le sue keyword, non
        // si trovava mai nulla, anche quando il pool conteneva temi di forte interesse generale
        // su cui il personaggio avrebbe comunque potuto dire la sua. Stesso pool già scaricato
        // (nessuna chiamata YouTube aggiuntiva, search.list resta a zero unità extra), stesso
        // filtro di rischio: cambia solo il criterio del giudizio LLM (judge($inNiche: false)).
        $generalCandidates = collect($pool)
            ->unique('video_id')
            ->sortByDesc('view_count')
            ->take(self::MAX_CANDIDATES_PER_CHARACTER)
            ->values()
            ->all();

        if (empty($generalCandidates)) {
            return;
        }

        $this->tryCreateFromCandidates($character, $generalCandidates, inNiche: false);
    }

    /**
     * @return bool true se è stato creato un HotTopic pending (giudicato rilevante e non a rischio).
     */
    private function tryCreateFromCandidates(Character $character, array $candidates, bool $inNiche): bool
    {
        try {
            $judged = $this->judge($character, $candidates, $inNiche);
        } catch (Throwable $e) {
            Log::warning('CheckHotTopics: giudizio LLM fallito', ['character_id' => $character->id, 'in_niche' => $inNiche, 'error' => $e->getMessage()]);
            return false;
        }

        if (! $judged['rilevante'] || in_array($judged['categoria_rischio'], self::RISK_CATEGORIES, true)) {
            return false;
        }

        $chosen = $judged['indice_scelto'] ? ($candidates[$judged['indice_scelto'] - 1] ?? null) : null;

        HotTopic::create([
            'character_id' => $character->id,
            'title' => $judged['titolo'],
            'summary' => $judged['sintesi'],
            'source_label' => $chosen ? "YouTube: {$chosen['channel_title']}" : null,
            'source_url' => $chosen['url'] ?? null,
            'status' => 'pending',
            'discovered_at' => now(),
            'in_niche' => $inNiche,
        ]);

        $etichetta = $inNiche ? '' : ' (fuori nicchia, forte appeal pubblico)';
        $this->info("Tema caldo trovato per {$character->name}{$etichetta}: {$judged['titolo']}");

        return true;
    }

    /**
     * Stadio 1 (grezzo, zero costo): il pool è già "mostPopular", quindi già virale per
     * definizione — qui non si verifica la popolarità, ma se la parola chiave del personaggio
     * compare davvero nel titolo o nella descrizione del video.
     */
    private function matchesKeyword(array $video, string $keyword): bool
    {
        $haystack = mb_strtolower($video['title'].' '.$video['description']);
        return $keyword !== '' && str_contains($haystack, mb_strtolower($keyword));
    }

    /**
     * @param bool $inNiche true per la traccia "pertinenza alla nicchia stretta" (comportamento
     *   originale), false per la traccia generalista (richiesta utente, 13 ottobre 2026): stesso
     *   filtro di rischio in entrambi i casi, cambia solo il criterio di "rilevante" — qui basta
     *   un forte interesse pubblico/curiosità su cui {$character->name} possa dire la sua con la
     *   propria voce, anche restando fuori dalla sua nicchia abituale.
     */
    private function judge(Character $character, array $candidates, bool $inNiche = true): array
    {
        $niche = implode(', ', $character->editorialSettings?->niche ?? []);
        $elenco = collect($candidates)
            ->map(fn (array $c, int $i) => ($i + 1).". [{$c['channel_title']}] \"{$c['title']}\" ({$c['view_count']} visualizzazioni)"
                .($c['description'] !== '' ? " — {$c['description']}" : ''))
            ->implode("\n");

        $criterio = $inNiche
            ? "Il tuo compito è scegliere se UNO di questi video rappresenta un tema abbastanza pertinente e \"caldo\" da meritare un post di reazione da parte di {$character->name} oggi — non basta che il video sia popolare, deve essere davvero legato alla sua nicchia, non un argomento generico buono per chiunque."
            : "Questa volta NON stiamo cercando pertinenza con la sua nicchia stretta ({$niche}): il tuo compito è scegliere se UNO di questi video rappresenta comunque un tema di forte interesse pubblico, curiosità o attualità genuina — qualcosa di cui la gente parla davvero oggi — abbastanza interessante da meritare un commento genuino da parte di {$character->name} nella propria voce, anche se è fuori tema rispetto alla sua nicchia abituale. Scarta solo ciò che è puro intrattenimento senza alcun aggancio reale con l'attualità/una notizia/una curiosità degna di nota (es. un videoclip musicale, un trailer, un video virale puramente comico), non per il solo fatto di non riguardare la sua nicchia.";

        $prompt = <<<TXT
Sei un filtro editoriale per {$character->name}, un personaggio la cui nicchia è: {$niche}.
Di seguito una lista di video di tendenza su YouTube. {$criterio}

{$elenco}

Restituisci un JSON con questi campi:
- rilevante: true solo se ALMENO UNO dei video sopra soddisfa il criterio sopra; false altrimenti
- categoria_rischio: uno tra "nessuna", "politica_elettorale", "tragedia", "cronaca_nera" — classifica onestamente il tema scelto anche se questo lo scarterebbe: non ammorbidire la classificazione per farlo passare
- titolo: se rilevante, un titolo breve e neutro del tema scelto (max 10 parole), altrimenti stringa vuota
- sintesi: se rilevante, 2-3 frasi neutre che riassumono il tema per chi deve scriverci un post, altrimenti stringa vuota
- indice_scelto: se rilevante, il numero (1, 2, 3...) del video scelto nella lista sopra, altrimenti null

Restituisci ESCLUSIVAMENTE il JSON, nessun markdown, nessun testo prima o dopo.
TXT;

        $response = Http::withToken(config('services.openai.key'))
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.text_model'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'giudizio_tema_caldo',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['rilevante', 'categoria_rischio', 'titolo', 'sintesi', 'indice_scelto'],
                            'properties' => [
                                'rilevante' => ['type' => 'boolean'],
                                'categoria_rischio' => ['type' => 'string', 'enum' => ['nessuna', 'politica_elettorale', 'tragedia', 'cronaca_nera']],
                                'titolo' => ['type' => 'string'],
                                'sintesi' => ['type' => 'string'],
                                'indice_scelto' => ['type' => ['integer', 'null']],
                            ],
                        ],
                    ],
                ],
            ])
            ->throw()
            ->json();

        $content = $response['choices'][0]['message']['content'] ?? null;
        if (! $content) {
            throw new RuntimeException('Il giudizio tema caldo non ha restituito contenuto.');
        }
        $data = json_decode($content, true);
        if (! is_array($data)) {
            throw new RuntimeException('Il giudizio tema caldo non ha restituito un JSON valido: '.$content);
        }

        return $data;
    }
}
