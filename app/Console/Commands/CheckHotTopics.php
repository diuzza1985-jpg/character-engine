<?php

namespace App\Console\Commands;

use App\Models\Character;
use App\Models\HotTopic;
use App\Services\RedditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Formato "tema_caldo" (documento temi-attualità, sez. 3): un personaggio reagisce a
 * controversie/discussioni virali su Reddit pertinenti alla sua nicchia. Filtro a due stadi
 * (sez. 3.C):
 *  1) grezzo — punteggio/velocità di commenti sopra soglia, nessuna chiamata LLM ancora;
 *  2) giudizio LLM dedicato che decide se è davvero abbastanza rilevante/"caldo", con una
 *     guardia esplicita lato codice sulle categorie a rischio — non lasciata al solo giudizio
 *     libero del modello.
 * Se approvato, crea un HotTopic "pending": la stessa "disponibilità per il prossimo ciclo
 * editoriale" già usata per $diarioDisponibile, ma con contenuto vero (titolo/sintesi) invece di
 * un semplice booleano, quindi persistita invece che calcolata al volo.
 */
class CheckHotTopics extends Command
{
    protected $signature = 'character:check-hot-topics';

    protected $description = 'Controlla le subreddit di ogni personaggio per temi "caldi" pertinenti alla sua nicchia e marca una disponibilità per il prossimo ciclo editoriale';

    private const MIN_SCORE = 500;

    private const MIN_COMMENT_VELOCITY_PER_HOUR = 15.0;

    private const MAX_CANDIDATES_PER_CHARACTER = 3;

    // Oltre questa età un tema "pending" mai scelto dal cervello editoriale scade: un tema
    // caldo di 4 giorni fa non è più caldo, non deve restare disponibile all'infinito.
    private const MAX_TOPIC_AGE_DAYS = 3;

    // Scartate SEMPRE dal codice, indipendentemente dal giudizio "rilevante" del modello (sez.
    // 3.C: "un filtro esplicito che scarti a monte... non lasciare la decisione solo al giudizio
    // libero dell'LLM").
    private const RISK_CATEGORIES = ['politica_elettorale', 'tragedia', 'cronaca_nera'];

    public function handle(RedditService $reddit): int
    {
        $this->expireStaleTopics();

        $characters = Character::whereHas('editorialSettings', function ($q) {
            $q->whereNotNull('hot_topic_subreddits');
        })->get();

        foreach ($characters as $character) {
            $this->processCharacter($character, $reddit);
        }

        return self::SUCCESS;
    }

    private function expireStaleTopics(): void
    {
        HotTopic::where('status', 'pending')
            ->where('discovered_at', '<', now()->subDays(self::MAX_TOPIC_AGE_DAYS))
            ->update(['status' => 'expired']);
    }

    private function processCharacter(Character $character, RedditService $reddit): void
    {
        // Un tema alla volta (stesso principio del diario centellinato): se ce n'è già uno
        // pending non ancora usato, non se ne cerca un altro finché non viene consumato o scade —
        // evita di accumulare temi "caldi" ormai stantii in coda.
        if (HotTopic::where('character_id', $character->id)->where('status', 'pending')->exists()) {
            return;
        }

        $subreddits = $character->editorialSettings?->hot_topic_subreddits ?? [];
        if (empty($subreddits)) {
            return;
        }

        $candidates = [];
        foreach ($subreddits as $subreddit) {
            try {
                foreach ($reddit->fetchListing($subreddit, 'hot', 15) as $post) {
                    if ($this->passesCrudeFilter($post)) {
                        $candidates[] = $post;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('CheckHotTopics: fetch Reddit fallito', [
                    'character_id' => $character->id, 'subreddit' => $subreddit, 'error' => $e->getMessage(),
                ]);
            }
        }

        if (empty($candidates)) {
            return;
        }

        usort($candidates, fn (array $a, array $b) => $b['score'] <=> $a['score']);
        $candidates = array_slice($candidates, 0, self::MAX_CANDIDATES_PER_CHARACTER);

        try {
            $judged = $this->judge($character, $candidates);
        } catch (Throwable $e) {
            Log::warning('CheckHotTopics: giudizio LLM fallito', ['character_id' => $character->id, 'error' => $e->getMessage()]);
            return;
        }

        if (! $judged['rilevante'] || in_array($judged['categoria_rischio'], self::RISK_CATEGORIES, true)) {
            return;
        }

        HotTopic::create([
            'character_id' => $character->id,
            'title' => $judged['titolo'],
            'summary' => $judged['sintesi'],
            'source_label' => $judged['fonte_label'] ?? null,
            'source_url' => $judged['fonte_url'] ?? null,
            'status' => 'pending',
            'discovered_at' => now(),
        ]);

        $this->info("Tema caldo trovato per {$character->name}: {$judged['titolo']}");
    }

    private function passesCrudeFilter(array $post): bool
    {
        if ($post['score'] >= self::MIN_SCORE) {
            return true;
        }
        $ageHours = max(1, (time() - $post['created_utc']) / 3600);

        return ($post['num_comments'] / $ageHours) >= self::MIN_COMMENT_VELOCITY_PER_HOUR;
    }

    private function judge(Character $character, array $candidates): array
    {
        $niche = implode(', ', $character->editorialSettings?->niche ?? []);
        $elenco = collect($candidates)
            ->map(fn (array $c, int $i) => ($i + 1).". [r/{$c['subreddit']}] \"{$c['title']}\" (punteggio {$c['score']}, {$c['num_comments']} commenti)")
            ->implode("\n");

        $prompt = <<<TXT
Sei un filtro editoriale per {$character->name}, un personaggio la cui nicchia è: {$niche}.
Di seguito una lista di post "hot" su Reddit dalle sue subreddit di riferimento. Il tuo compito è scegliere se UNO di questi temi è abbastanza pertinente e "caldo" da meritare un post di reazione da parte di {$character->name} oggi — non tutti i temi popolari vanno bene, deve essere davvero legato alla sua nicchia, non un argomento generico buono per chiunque.

{$elenco}

Restituisci un JSON con questi campi:
- rilevante: true solo se ALMENO UNO dei temi sopra è genuinamente pertinente alla nicchia di {$character->name} e abbastanza significativo da motivare un post; false altrimenti (es. tutti fuori tema, troppo interni alla community, o poco interessanti per un pubblico più ampio)
- categoria_rischio: uno tra "nessuna", "politica_elettorale", "tragedia", "cronaca_nera" — classifica onestamente il tema scelto anche se questo lo scarterebbe: non ammorbidire la classificazione per farlo passare
- titolo: se rilevante, un titolo breve e neutro del tema scelto (max 10 parole), altrimenti stringa vuota
- sintesi: se rilevante, 2-3 frasi neutre che riassumono il tema per chi deve scriverci un post, altrimenti stringa vuota
- fonte_label: se rilevante, l'etichetta "r/nomesubreddit" del post scelto, altrimenti null
- fonte_url: sempre null (non lo conosci da questa lista, verrà aggiunto altrove)

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
                            'required' => ['rilevante', 'categoria_rischio', 'titolo', 'sintesi', 'fonte_label', 'fonte_url'],
                            'properties' => [
                                'rilevante' => ['type' => 'boolean'],
                                'categoria_rischio' => ['type' => 'string', 'enum' => ['nessuna', 'politica_elettorale', 'tragedia', 'cronaca_nera']],
                                'titolo' => ['type' => 'string'],
                                'sintesi' => ['type' => 'string'],
                                'fonte_label' => ['type' => ['string', 'null']],
                                'fonte_url' => ['type' => ['string', 'null']],
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

        // fonte_url va preso dal candidato scelto (il modello non lo conosce, gli è stato
        // deliberatamente nascosto per non fargli inventare URL plausibili ma falsi) — matcha
        // per fonte_label ("r/subreddit") sul primo candidato di quella subreddit tra i pochi
        // passati, sufficiente perché sono al massimo 3.
        if (! empty($data['fonte_label'])) {
            $subredditLabel = str_replace('r/', '', $data['fonte_label']);
            $match = collect($candidates)->firstWhere('subreddit', $subredditLabel);
            $data['fonte_url'] = $match['permalink'] ?? null;
        }

        return $data;
    }
}
