<?php
namespace App\Services;
use App\Models\Character;
use App\Models\CharacterRelationship;
use App\Models\Generation;
use App\Models\Post;
use App\Models\Storyline;
use App\Models\TimelineEntry;
use App\Support\TemporalContext;

/**
 * Assembla in una struttura unica tutto ciò che serve al cervello editoriale (11.7) per decidere
 * cosa pubblicare oggi. Puramente deterministico: nessuna chiamata esterna, solo lettura DB.
 */
class EditorialContextBuilder
{
    private const MAX_STORYLINES = 3;
    // Bug reale segnalato dall'utente (13 ottobre 2026): con un tetto di 5 e last_interaction_at
    // NULL per quasi tutte le relazioni (mai aggiornato altrove nel codice), l'ordinamento
    // orderByDesc('last_interaction_at') lasciava Sara e Marta fuori dal taglio quasi sempre,
    // dietro agli animali domestici (stesso valore NULL, ma in pratica ordinati prima per
    // id/inserimento) — il cervello editoriale non le vedeva proprio in RELAZIONI RILEVANTI,
    // a prescindere da qualunque istruzione di varietà nel prompt. 10 copre comodamente il
    // numero reale di relazioni di oggi (7) con margine di crescita, senza reintrodurre lo
    // stesso taglio arbitrario.
    private const MAX_RELATIONSHIPS = 10;
    private const MAX_RECENT_POSTS = 12;
    private const MAX_RECENT_TIMELINE = 15;
    private const MAX_MEMORIES = 3;
    private const MAX_TOPIC_LOOKBACK = 8;
    // Finestra più lunga di MAX_TOPIC_LOOKBACK: scegliere di mostrare una relazione in foto è un
    // evento molto più raro di scegliere un topic (12/74 decisioni nel campione che ha fatto
    // emergere il problema, sez. sotto) — una finestra corta come quella dei topic non darebbe
    // segnale a sufficienza per accorgersi dello squilibrio.
    private const MAX_RELATIONSHIP_LOOKBACK = 25;
    private const MAX_FORMAT_LOOKBACK = 10;

    public function __construct(
        private readonly MemoryRecallSelector $memoryRecallSelector,
        private readonly NewsDigestService $newsDigest,
    ) {
    }

    public function build(Character $character): array
    {
        return [
            'personaggio' => [
                'nome' => $character->name,
                'slug' => $character->slug,
            ],
            'oggi' => [
                // Ora italiana reale (TemporalContext::now()), non l'UTC del server — bug reale
                // trovato e corretto insieme all'orario di pubblicazione (vedi TemporalContext).
                'data' => TemporalContext::now()->toDateString(),
                'ora' => TemporalContext::now()->format('H:i'),
                'momento_del_giorno' => TemporalContext::timeOfDay(),
                'stagione' => TemporalContext::season(),
                // Contesto calendariale (11.29): serve al cervello editoriale per non scrivere un
                // contenuto "giornata di lavoro/ufficio" in un weekend o in una festività —
                // stesso principio di season()/timeOfDay(), Europe/Rome esplicito, nessuna
                // chiamata esterna.
                'giorno_settimana' => TemporalContext::giornoSettimana(),
                'weekend' => TemporalContext::isWeekend(),
                'festivita' => TemporalContext::festivita(),
            ],
            'bible' => $this->buildBible($character),
            'profilo_visivo' => $this->buildVisualProfile($character),
            'stato_interno' => $this->buildInternalState($character),
            'storyline_rilevanti' => $this->buildRelevantStorylines($character),
            'relazioni_rilevanti' => $this->buildRelevantRelationships($character),
            'contenuti_recenti' => $this->buildRecentPosts($character),
            'varieta_argomenti' => $this->buildTopicFrequency($character),
            // Bug reale segnalato dall'utente (13 ottobre 2026): su 74 decisioni editoriali di
            // Sofia, il marito è comparso fisicamente in foto solo 3 volte e la figlia Sara una
            // sola volta, contro le 7 del cane Pippo — nessun dato tracciava questo squilibrio
            // (a differenza di varieta_argomenti sopra, che esiste apposta per i topic), quindi
            // il cervello editoriale non aveva modo di accorgersene da solo.
            'varieta_personaggi_coinvolti' => $this->buildRelationshipAppearanceFrequency($character),
            // Stesso bug, lato formato: "post" (foto + testo overlay) dominava nettamente sugli
            // altri formati disponibili senza che nulla lo segnalasse — stesso principio di
            // varieta_argomenti, applicato al formato invece che al topic.
            'varieta_formati' => $this->buildFormatFrequency($character),
            'vita_recente' => $this->buildRecentTimeline($character),
            'possibili_ricordi' => $this->buildRelevantMemories($character),
            // Spunti di attualità/curiosità generici (non legati alla nicchia del personaggio,
            // a differenza del "tema caldo" di CheckHotTopics): bug reale segnalato dall'utente,
            // la ricerca di notizie del giorno esisteva solo nella pipeline legacy
            // (NewsDigestService, via LifeEvent.news_query) e non è mai stata portata nel ciclo
            // editoriale basato su cervello — qui è materiale disponibile ogni giorno per
            // qualunque formato, non solo per "tema_caldo".
            'notizie_del_giorno' => $this->buildGeneralNewsDigest(),
        ];
    }

    /**
     * Riusa lo stesso elenco di sezioni già curato per instagram_post (config/purposes.php):
     * è già la selezione "cosa serve davvero per decidere/scrivere un contenuto pubblico".
     */
    private function buildBible(Character $character): string
    {
        $sectionKeys = config('purposes.instagram_post', []);
        $sections = $character->bibleSections()
            ->whereIn('section_key', $sectionKeys)
            ->get()
            ->keyBy('section_key');

        $documentation = '';
        foreach ($sectionKeys as $key) {
            $section = $sections->get($key);
            if (! $section || trim($section->content) === '') {
                continue;
            }
            $documentation .= "\n\n--- SEZIONE: {$key} ---\n\n" . $section->content;
        }

        return trim($documentation);
    }

    private function buildVisualProfile(Character $character): ?array
    {
        $profile = $character->visualProfile;
        if (! $profile) {
            return null;
        }

        return array_filter([
            'età' => $profile->age_description,
            'viso' => $profile->face_description,
            'capelli' => $profile->hair_description,
            'guardaroba' => $profile->wardrobe_notes,
            'regole_visive' => $profile->visual_rules_text,
        ]);
    }

    private function buildInternalState(Character $character): array
    {
        $state = $character->characterState;
        if (! $state) {
            return ['mood' => null, 'drives' => null, 'last_tick_at' => null];
        }

        return [
            'mood' => [
                'energia' => round($state->mood_energia, 2),
                'valenza' => round($state->mood_valenza, 2),
                'stress' => round($state->mood_stress, 2),
            ],
            'drives' => $state->drives,
            'last_tick_at' => $state->last_tick_at?->toDateTimeString(),
        ];
    }

    /**
     * Solo storyline "aperta"/"in_pausa" hanno pressione narrativa (11.3); le top N per pressione.
     */
    private function buildRelevantStorylines(Character $character): array
    {
        $windowDays = config('life_engine.storyline_pressure_window_days');

        return $character->storylines()
            ->whereIn('status', ['aperta', 'in_pausa'])
            ->get()
            ->map(fn (Storyline $s) => [
                'id' => $s->id,
                'titolo' => $s->title,
                'stato' => $s->status,
                'importanza' => $s->importance,
                'pressione' => round($s->pressure($windowDays), 2),
                'giorni_da_ultimo_aggiornamento' => $s->daysSinceLastUpdate(),
            ])
            ->sortByDesc('pressione')
            ->take(self::MAX_STORYLINES)
            ->values()
            ->all();
    }

    private function buildRelevantRelationships(Character $character): array
    {
        return $character->relationships()
            ->with('relatedCharacter.visualProfile')
            ->orderByDesc('last_interaction_at')
            ->take(self::MAX_RELATIONSHIPS)
            ->get()
            ->map(fn (CharacterRelationship $r) => [
                'id' => $r->id,
                'nome' => $r->name,
                'tipo' => $r->type,
                'sentiment' => round($r->sentiment, 2),
                'ultima_interazione' => $r->last_interaction_at?->toDateString(),
                // Fase 3 mondo narrativo: solo le relazioni collegate a un Character reale
                // (related_character_id, Fase 1) sono candidabili per comparire fisicamente
                // insieme al personaggio principale nella stessa immagine — il resto resta
                // puramente narrativo, come sempre.
                'puo_comparire_in_foto' => (bool) $r->related_character_id,
                'aspetto_visivo' => $this->buildRelatedVisualSummary($r->relatedCharacter),
            ])
            ->all();
    }

    /**
     * Bug reale osservato (Fase 3, mondo narrativo): quando una relazione compare fisicamente
     * insieme al personaggio principale, prompt_immagine si affidava SOLO alla foto di
     * riferimento passata al modello di generazione multi-soggetto, senza alcun rinforzo
     * testuale — il modello non sempre preserva fedelmente ogni dettaglio della reference (es.
     * lunghezza capelli). Un breve riassunto testuale del profilo visivo di chi è collegato
     * (se esiste) dà al cervello editoriale qualcosa da scrivere esplicitamente in
     * prompt_immagine, a rinforzo della foto.
     */
    private function buildRelatedVisualSummary(?Character $related): ?string
    {
        if (! $related || ! $related->visualProfile) {
            return null;
        }

        $parts = array_filter([
            $related->visualProfile->hair_description,
            $related->visualProfile->face_description,
        ]);

        return $parts ? implode(' ', $parts) : null;
    }

    /**
     * Testo pieno della caption, non un estratto (era Str::limit(..., 120) fino a poco fa):
     * un bersaglio comico o un dettaglio da non ripetere finisce tipicamente in fondo alla
     * caption (la punchline), non nei primi 120 caratteri — un estratto corto rendeva
     * impossibile per il cervello editoriale accorgersi di battute già fatte di recente.
     */
    private function buildRecentPosts(Character $character): array
    {
        return $character->posts()
            ->with('generation')
            ->latest('created_at')
            ->take(self::MAX_RECENT_POSTS)
            ->get()
            ->map(fn (Post $p) => [
                'data' => $p->created_at->toDateString(),
                'formato' => $p->narrative_format ?? $p->media_type,
                'stato' => $p->status,
                'caption' => $p->caption ?: null,
                // Bug reale osservato (agosto 2026): senza questo, il cervello editoriale non
                // aveva NESSUNA visibilità su cosa avesse mostrato visivamente di recente (solo
                // sul testo delle caption) — stesso identico abbigliamento ("shirt and jeans") e
                // la stessa scena da tavolo ("laptop + quaderno + caffè freddo") ripetuti quasi
                // identici per settimane, senza che nulla lo segnalasse. Stesso principio già
                // applicato al testo (formatContenutiRecenti sotto), ora anche al visivo.
                'prompt_immagine' => $p->generation?->output['prompt_immagine'] ?? null,
            ])
            ->all();
    }

    /**
     * Conteggio deterministico "quante delle ultime N pubblicazioni erano su questo topic"
     * (Fase 0 mondo-narrativo): sostituisce il solo auto-giudizio del modello sul testo libero
     * delle caption con un dato reale calcolato qui. Generico per costruzione — legge solo
     * TimelineEntry.topic di QUESTO personaggio, qualunque valore contenga (nessuna categoria
     * fissa nel codice): funziona identico per un personaggio che non abbia mai avuto un topic
     * in comune con nessun altro.
     *
     * @return array<string, int> topic => conteggio, ordinato dal più frequente
     */
    private function buildTopicFrequency(Character $character): array
    {
        return $character->timelineEntries()
            ->whereNotNull('topic')
            ->where('topic', '!=', '')
            ->latest('created_at')
            ->take(self::MAX_TOPIC_LOOKBACK)
            ->pluck('topic')
            ->countBy()
            ->sortDesc()
            ->all();
    }

    /**
     * Conteggio deterministico di quante volte ciascuna relazione "può comparire in foto"
     * (related_character_id valorizzato) è stata davvero scelta come personaggio_coinvolto_id
     * nelle ultime decisioni, più quante volte non è stata scelta nessuna — stesso principio di
     * buildTopicFrequency sopra, applicato a CHI compare in foto invece che a COSA si racconta.
     * Legge Generation.output (dove personaggio_coinvolto_id viene scritto da
     * EditorialBrainService::decide()), non Post: una decisione può fallire dopo (immagine) senza
     * che questo conteggio ne risenta, è la scelta editoriale che conta qui, non l'esito tecnico.
     *
     * @return array{per_relazione: array<string,int>, nessuno: int, totale: int}
     */
    private function buildRelationshipAppearanceFrequency(Character $character): array
    {
        $eligibili = $character->relationships()
            ->whereNotNull('related_character_id')
            ->get(['id', 'name']);

        if ($eligibili->isEmpty()) {
            return ['per_relazione' => [], 'nessuno' => 0, 'totale' => 0];
        }

        $perRelazione = $eligibili->mapWithKeys(fn (CharacterRelationship $r) => ["{$r->name} (id {$r->id})" => 0])->all();
        $nessuno = 0;

        $decisioni = Generation::where('character_id', $character->id)
            ->where('purpose', 'editorial_decision')
            ->where('status', 'completed')
            ->latest('created_at')
            ->take(self::MAX_RELATIONSHIP_LOOKBACK)
            ->pluck('output');

        $totale = 0;
        foreach ($decisioni as $output) {
            if (! is_array($output) || empty($output['decisione']) || $output['decisione'] !== 'pubblica') {
                continue; // "non_pubblicare" non è una scelta su chi comparire in foto.
            }
            $totale++;
            $relId = $output['personaggio_coinvolto_id'] ?? null;
            $match = $relId ? $eligibili->firstWhere('id', $relId) : null;
            if ($match) {
                $perRelazione["{$match->name} (id {$match->id})"]++;
            } else {
                $nessuno++;
            }
        }

        return ['per_relazione' => $perRelazione, 'nessuno' => $nessuno, 'totale' => $totale];
    }

    /**
     * Stesso principio di buildTopicFrequency, applicato al formato (narrative_format) invece
     * che al topic: bug reale osservato, "post" (foto singola + testo overlay) dominava senza che
     * nulla lo segnalasse numericamente al cervello editoriale, a differenza dei topic.
     *
     * @return array<string,int> formato => conteggio, ordinato dal più frequente
     */
    private function buildFormatFrequency(Character $character): array
    {
        return $character->posts()
            ->whereNotNull('narrative_format')
            ->where('narrative_format', '!=', 'story_share') // non è una scelta editoriale propria, solo un derivato automatico
            ->latest('created_at')
            ->take(self::MAX_FORMAT_LOOKBACK)
            ->pluck('narrative_format')
            ->countBy()
            ->sortDesc()
            ->all();
    }

    /**
     * Digest generico di attualità/curiosità (NewsDigestService, già esistente ma finora usato
     * solo dalla pipeline legacy) — non legato alla nicchia del personaggio, a differenza del
     * "tema caldo" di CheckHotTopics: qui l'obiettivo non è "un tema su cui reagire con un post
     * dedicato", ma semplice materiale grezzo di cosa succede oggi nel mondo, disponibile come
     * spunto per qualunque formato. Cache di un giorno: è lo stesso digest per qualunque
     * personaggio lo richieda nella stessa giornata, non ha senso richiamare l'API esterna a ogni
     * ciclo di ogni personaggio (ogni 15 minuti, routes/console.php).
     */
    private function buildGeneralNewsDigest(): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            'editorial_context:notizie_del_giorno:' . TemporalContext::now()->toDateString(),
            now()->addHours(6),
            fn () => $this->newsDigest->fetchGeneralDigest(),
        );
    }

    private function buildRecentTimeline(Character $character): array
    {
        return $character->timelineEntries()
            ->latest('created_at')
            ->take(self::MAX_RECENT_TIMELINE)
            ->get()
            ->map(fn (TimelineEntry $t) => [
                'titolo' => $t->title,
                'topic' => $t->topic,
                'giorni_fa' => (int) $t->created_at->diffInDays(now()),
            ])
            ->all();
    }

    /**
     * Eventi passati sopra la soglia di "vale la pena ricordarlo oggi" (MemoryRecallSelector).
     * Non è un obbligo per il cervello editoriale: è materiale disponibile, come le storyline
     * con pressione alta — la decisione se usarlo resta qualitativa (sezione 2.1 del documento
     * "motore di memoria narrativa").
     */
    private function buildRelevantMemories(Character $character): array
    {
        return $this->memoryRecallSelector->candidatesFor($character)
            ->take(self::MAX_MEMORIES)
            ->map(fn (array $c) => [
                'id' => $c['entry']->id,
                'titolo' => $c['entry']->title,
                'topic' => $c['entry']->topic,
                'scena' => $c['entry']->scene,
                'giorni_fa' => $c['giorni_trascorsi'],
                'punteggio' => round($c['punteggio'], 2),
            ])
            ->all();
    }
}
