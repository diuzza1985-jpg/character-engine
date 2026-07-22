<?php
namespace App\Services;
use App\Models\Character;
use App\Models\CharacterEditorialSettings;
use App\Models\EditorialDecision;
use App\Models\Generation;
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
            $decision = $this->brain->decide($character, $context, $forcePublish, $daysSinceLastPost, $settings->max_giorni_silenzio, $instructions, $diarioDisponibile);
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

    private function mapFormatoToMediaType(?string $formato): string
    {
        // "post", "diario" e "oggetto" sono tutti immagine singola per Instagram — la differenza
        // narrativa tra loro vive in narrative_format, non in media_type. carousel/reel/story restano
        // distinti perché richiedono davvero un trattamento diverso in pubblicazione.
        //
        // "reel" non è più un formato che il cervello editoriale può decidere (sez. 12.5,
        // EditorialBrainService::formatoEnum) perché non esiste una pipeline di generazione
        // video dietro — questo passthrough per "reel" resta morto ma innocuo: se mai riappare
        // qui è perché formatoEnum è stato toccato senza completare prima una vera pipeline video.
        return in_array($formato, ['post', 'diario', 'oggetto'], true) ? 'image' : ($formato ?? 'image');
    }
}
