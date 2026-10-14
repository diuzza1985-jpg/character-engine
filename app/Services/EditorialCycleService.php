<?php
namespace App\Services;
use App\Models\Character;
use App\Models\CharacterEditorialSettings;
use App\Models\EditorialDecision;
use App\Models\Generation;
use App\Models\HotTopic;
use App\Models\Post;
use App\Models\Storyline;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Orchestratore del ciclo editoriale (11.7): assembla il contesto, chiama il cervello
 * editoriale, registra generations/editorial_decisions e, solo se la decisione è
 * "pubblica", crea un post draft. Nessuna generazione immagine, nessun credito scalato,
 * nessuna pubblicazione Instagram: fuori scope per questa prima versione (vedi sessione).
 */
class EditorialCycleService
{
    // Finestra di riuso di un post 'failed' (findReusableFailedPost sotto): oltre questo numero
    // di giorni il contenuto rischia di essere narrativamente stantio (giorno della
    // settimana/stagione citati nel prompt_immagine, "tema_caldo" ormai vecchio, storyline
    // nel frattempo avanzata) più di quanto valga il risparmio di una chiamata OpenAI — meglio
    // una decisione nuova a quel punto.
    private const REUSE_WINDOW_DAYS = 3;

    public function __construct(
        private EditorialContextBuilder $contextBuilder,
        private EditorialBrainService $brain,
    ) {}

    /**
     * @param ?string $instructions istruzioni specifiche del giorno (es. da ScheduledPost.instructions
     *   quando lo slot viene da un panello di programmazione) — indirizzano il contenuto, non
     *   forzano la pubblicazione (quello resta solo max_giorni_silenzio).
     * @return array{generation: Generation, decision: array|null, editorial_decision: EditorialDecision|null, post: Post|null}
     */
    public function run(Character $character, ?string $instructions = null): array
    {
        // Riuso di una decisione già pagata (bug reale, 8-13 ottobre 2026: fal.ai bloccato per
        // credito esaurito, 5 post rimasti 'failed' con caption/prompt_immagine già prodotti da
        // OpenAI ma nessuna immagine) — PRIMA di chiamare di nuovo $this->brain->decide() (una
        // chiamata OpenAI a pagamento), controlliamo se esiste già un post fallito compatibile
        // con tutto il testo pronto, fallito solo per l'immagine. Solo per il flusso automatico
        // senza istruzioni specifiche del giorno: con $instructions valorizzate l'utente vuole
        // davvero un contenuto nuovo che le rispetti, non del materiale vecchio deciso senza.
        if ($instructions === null && $reused = $this->findReusableFailedPost($character)) {
            return $reused;
        }

        $settings = CharacterEditorialSettings::firstOrCreate(
            ['character_id' => $character->id],
            ['max_giorni_silenzio' => 2, 'diario_ogni_n_post' => 20] // <-- diario_ogni_n_post aggiunto qui per esplicitezza; il default DB (20, sezione 2.1) copre comunque i record creati da altre strade
        );

        $lastPost = Post::where('character_id', $character->id)->latest('created_at')->first();
        $daysSinceLastPost = $lastPost ? (int) $lastPost->created_at->diffInDays(now()) : null;
        $forcePublish = $daysSinceLastPost === null || $daysSinceLastPost > $settings->max_giorni_silenzio;

        // Diario centellinato (character_editorial_settings.diario_ogni_n_post): mai fatto un
        // diario -> sempre disponibile, altrimenti conta i post pubblicati dopo l'ultimo.
        $lastDiarioPost = Post::where('character_id', $character->id)
            ->where('narrative_format', 'diario')
            ->latest('created_at')
            ->first();

        $postsSinceLastDiario = $lastDiarioPost
            ? Post::where('character_id', $character->id)->where('created_at', '>', $lastDiarioPost->created_at)->count()
            : PHP_INT_MAX; // mai fatto un diario -> sempre disponibile

        $diarioDisponibile = $postsSinceLastDiario >= $settings->diario_ogni_n_post;

        // Tema caldo (documento temi-attualità, sez. 3): al massimo un HotTopic "pending" per
        // personaggio (character:check-hot-topics), stesso principio "disponibilità per il
        // prossimo ciclo" di $diarioDisponibile ma con contenuto vero (titolo/sintesi) da passare
        // al cervello editoriale, non solo un booleano — da qui la riga persistita invece di un
        // conteggio calcolato al volo.
        $hotTopic = HotTopic::where('character_id', $character->id)->where('status', 'pending')->latest('discovered_at')->first();
        $temaCaldo = $hotTopic ? ['titolo' => $hotTopic->title, 'sintesi' => $hotTopic->summary, 'in_niche' => $hotTopic->in_niche] : null;

        $context = $this->contextBuilder->build($character);

        $generation = Generation::create([
            'character_id' => $character->id,
            'tenant_id' => $character->tenant_id,
            'purpose' => 'editorial_decision',
            'status' => 'processing',
            'input' => $context,
            'credits_charged' => 0,
        ]);

        try {
            $decision = $this->brain->decide($character, $context, $forcePublish, $daysSinceLastPost, $settings->max_giorni_silenzio, $instructions, $diarioDisponibile, $temaCaldo);
        } catch (Throwable $e) {
            Log::warning("Ciclo editoriale fallito per {$character->slug}: {$e->getMessage()}");
            $generation->update([
                'status' => 'failed',
                'output' => ['error' => $e->getMessage()],
                'completed_at' => now(),
            ]);

            return ['generation' => $generation, 'decision' => null, 'editorial_decision' => null, 'post' => null];
        }

        $generation->update([
            'status' => 'completed',
            'output' => $decision,
            'completed_at' => now(),
        ]);

        $storylineId = $decision['storyline_da_aggiornare']['storyline_id'] ?? null;

        $editorialDecision = EditorialDecision::create([
            'generation_id' => $generation->id,
            'decisione' => $decision['decisione'],
            'motivazione' => $decision['motivazione'],
            'storyline_id' => $storylineId,
            'used_news' => $decision['used_news'] ?? false,
        ]);

        if ($storylineId) {
            $this->applyStorylineUpdate($character, $storylineId, $decision['storyline_da_aggiornare']);
        }

        $post = null;
        if ($decision['decisione'] === 'pubblica') {
            $post = Post::create([
                'character_id' => $character->id,
                'generation_id' => $generation->id,
                'platform' => 'instagram',
                'media_type' => $this->mapFormatoToMediaType($decision['formato']),
                'narrative_format' => $decision['formato'],
                'status' => 'draft',
                'caption' => $decision['testo'] ?? '',
                'media_urls' => [],
            ]);

            if ($hotTopic && $decision['formato'] === 'tema_caldo') {
                $hotTopic->update(['status' => 'used', 'used_at' => now()]);
            }
        }

        return ['generation' => $generation, 'decision' => $decision, 'editorial_decision' => $editorialDecision, 'post' => $post];
    }

    /**
     * last_updated_at si muove solo qui, perché solo qui succede qualcosa di narrativamente
     * reale (una decisione editoriale che coinvolge la storyline) — mai per il solo fatto
     * di essere letta/valutata (regola già verificata nel motore di vita, 11.3).
     */
    private function applyStorylineUpdate(Character $character, int $storylineId, array $update): void
    {
        $storyline = Storyline::where('character_id', $character->id)->find($storylineId);
        if (! $storyline) {
            Log::warning("Cervello editoriale ha riferito storyline_id {$storylineId} inesistente per {$character->slug}, ignorato.");
            return;
        }

        $storyline->update([
            'status' => $update['nuovo_stato'],
            'last_updated_at' => now(),
            'resolution_notes' => $update['note'] !== '' ? $update['note'] : $storyline->resolution_notes,
        ]);
    }

    /**
     * @return array{generation: Generation, decision: array, editorial_decision: EditorialDecision|null, post: Post}|null
     */
    private function findReusableFailedPost(Character $character): ?array
    {
        $post = Post::where('character_id', $character->id)
            ->where('status', 'failed')
            // Esclude i post 'story_share': non nascono da una decisione editoriale propria
            // (PublishInstagramPostJob li crea riusando il generation_id del post originale già
            // pubblicato), riusarli qui li tratterebbe erroneamente come un formato "post"/
            // "carousel"/ecc. da illustrare da capo.
            ->where(function ($q) {
                $q->whereNull('narrative_format')->orWhere('narrative_format', '!=', 'story_share');
            })
            // Vuoto, non solo "non ancora pubblicato": un post con già delle media_urls non è
            // fallito per colpa dell'immagine, non è il caso che vogliamo recuperare qui.
            ->where(function ($q) {
                $q->whereNull('media_urls')->orWhereJsonLength('media_urls', 0);
            })
            ->whereNotNull('generation_id')
            ->where('created_at', '>=', now()->subDays(self::REUSE_WINDOW_DAYS))
            ->oldest('created_at') // il più vecchio compatibile prima: svuota il backlog in ordine
            ->first();

        if (! $post) {
            return null;
        }

        $generation = Generation::find($post->generation_id);
        if (! $generation || ! is_array($generation->output) || empty($generation->output['decisione'])) {
            return null; // Generation mancante/corrotta: meglio una decisione nuova che un crash.
        }

        Log::info("Riuso post #{$post->id} di {$character->slug} (fallito il {$post->created_at->toDateTimeString()}, decisione OpenAI già pagata) invece di una nuova chiamata OpenAI.");

        $post->update(['status' => 'draft']);

        return [
            'generation' => $generation,
            'decision' => $generation->output,
            'editorial_decision' => EditorialDecision::where('generation_id', $generation->id)->first(),
            'post' => $post,
        ];
    }

    private function mapFormatoToMediaType(?string $formato): string
    {
        // "post", "diario", "oggetto", "screenshot", "conversazione" e "tema_caldo" sono tutti
        // immagine singola per Instagram — la differenza narrativa tra loro vive in
        // narrative_format, non in media_type ("screenshot"/"conversazione" sono solo un
        // rendering diverso, "tema_caldo" solo una generazione standalone senza reference,
        // nessun trattamento di pubblicazione dedicato serve, a differenza di carousel/story).
        // carousel/reel/story restano distinti perché richiedono davvero un trattamento diverso
        // in pubblicazione.
        //
        // "reel" non è più un formato che il cervello editoriale può decidere (sez. 12.5,
        // EditorialBrainService::formatoEnum) perché non esiste una pipeline di generazione
        // video dietro — questo passthrough per "reel" resta morto ma innocuo: se mai riappare
        // qui è perché formatoEnum è stato toccato senza completare prima una vera pipeline video.
        return in_array($formato, ['post', 'diario', 'oggetto', 'screenshot', 'conversazione', 'tema_caldo'], true) ? 'image' : ($formato ?? 'image');
    }
}
