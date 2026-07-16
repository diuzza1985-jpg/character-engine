<?php
namespace App\Services;
use App\Models\Character;
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
     * @return array{generation: Generation, decision: array|null, editorial_decision: EditorialDecision|null, post: Post|null}
     */
    public function run(Character $character): array
    {
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
            $decision = $this->brain->decide($character, $context);
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
        // Lo schema esposto a OpenAI usa "post" (più naturale nel prompt); posts.media_type
        // usa invece "image" come da convenzione già in uso (vedi GenerateInstagramPostJob).
        return $formato === 'post' ? 'image' : ($formato ?? 'image');
    }
}
