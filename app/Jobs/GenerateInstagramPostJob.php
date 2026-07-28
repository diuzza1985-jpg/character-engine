<?php
namespace App\Jobs;
use App\Models\Character;
use App\Models\CharacterAsset;
use App\Models\Generation;
use App\Models\LifeEvent;
use App\Models\Post;
use App\Models\TimelineEntry;
use App\Services\EditorialCycleService;
use App\Services\FalImageService;
use App\Services\ImagePromptBuilder;
use App\Services\ImageTextOverlayService;
use App\Services\LifeEventSelector;
use App\Services\NewsDigestService;
use App\Services\OpenAiTextService;
use App\Services\PromptBuilder;
use App\Services\ReferenceImageSelector;
use App\Services\StoryComposerService;
use App\Support\TemporalContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
class GenerateInstagramPostJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 3;
    public $backoff = [30, 120, 600];
    private const COMPANION_MAP = [
        'pippo_leash' => 'pippo', 'walking_pippo' => 'pippo', 'minnie_blanket' => 'minnie',
        'sushi_aquarium' => 'sushi', 'fernando_toolbox' => 'fernando', 'fernando_project' => 'fernando',
    ];
    // Stessa lista base di ImagePromptBuilder::negativePrompt(), senza gli override
    // scena-specifici: il cervello editoriale non produce uno scene[] strutturato, solo un
    // prompt_immagine libero (stessa scelta già presa per la pubblicazione di Post#13, 11.13).
    private const CERVELLO_NEGATIVE_PROMPT = 'AI artifacts, extra fingers, plastic skin, fashion pose, luxury house, gaming setup, random stickers, different pets';
    // Stessi termini stagionali di ImagePromptBuilder::negativePrompt() (12.6): l'istruzione
    // positiva sulla stagione nel prompt del cervello editoriale (9a48fae) è scritta da GPT, che
    // può comunque sbagliare — stesso principio già applicato al rinforzo "no person" di
    // "oggetto" qui sotto. Prima di questo fix il cervello editoriale non aveva alcun backstop
    // lato negative prompt contro capi fuori stagione, a differenza della pipeline legacy.
    private const SEASONAL_NEGATIVE_TERMS = [
        'estate' => ['heavy sweater', 'wool jacket', 'winter coat', 'long sleeves', 'turtleneck'],
        'inverno' => ['short sleeves', 'tank top', 'summer dress'],
    ];
    public function __construct(
        private int $characterId,
        private string $postType = 'image',
        private ?int $forceLifeEventId = null,
        private ?string $instructions = null,
        private bool $useEditorialBrain = false
    ) {}
    public function handle(
        LifeEventSelector $lifeEventSelector, PromptBuilder $promptBuilder, OpenAiTextService $openAi,
        ImagePromptBuilder $imagePromptBuilder, ReferenceImageSelector $referenceSelector,
        FalImageService $fal, ImageTextOverlayService $overlay, NewsDigestService $newsDigest,
        EditorialCycleService $editorialCycle, StoryComposerService $storyComposer
    ): ?Post {
        $character = Character::findOrFail($this->characterId);

        if ($this->useEditorialBrain) {
            return $this->handleEditorialBrainFlow($character, $editorialCycle, $referenceSelector, $fal, $overlay, $storyComposer);
        }

        return $this->handleLegacyFlow(
            $character, $lifeEventSelector, $promptBuilder, $openAi,
            $imagePromptBuilder, $referenceSelector, $fal, $overlay, $newsDigest
        );
    }

    /**
     * Percorso invariato (pre-riconciliazione, 12.1): selezione fissa da life_events, usato
     * quando c'è un life event forzato a mano (scheduled_post/slot in modalità "manual") o dai
     * comandi di test. Nessuna lettura di mood/drives/storyline.
     */
    private function handleLegacyFlow(
        Character $character, LifeEventSelector $lifeEventSelector, PromptBuilder $promptBuilder,
        OpenAiTextService $openAi, ImagePromptBuilder $imagePromptBuilder, ReferenceImageSelector $referenceSelector,
        FalImageService $fal, ImageTextOverlayService $overlay, NewsDigestService $newsDigest
    ): ?Post {
        $generation = Generation::create([
            'character_id' => $character->id, 'tenant_id' => $character->tenant_id,
            'purpose' => 'instagram_post', 'status' => 'processing',
            'input' => ['post_type' => $this->postType, 'instructions' => $this->instructions], 'credits_charged' => 0,
        ]);
        try {
            $lifeEvent = $this->forceLifeEventId
                ? LifeEvent::findOrFail($this->forceLifeEventId)
                : $lifeEventSelector->pick($character);
            $news = $lifeEvent->news_query
                ? $newsDigest->fetchRecent($lifeEvent->news_query, $lifeEvent->news_category)
                : [];
            $textPrompt = $promptBuilder->buildInstagramPostPrompt($character, $lifeEvent, $this->postType, $news, $this->instructions);
            $post = $openAi->generate($textPrompt);
            if ($this->postType === 'carousel' && empty($post['carousel_slides'])) {
                throw new RuntimeException('Richiesto un carosello ma OpenAI non ha restituito carousel_slides.');
            }
            $imageBuilt = $imagePromptBuilder->build($character, $post['scene']);
            $reference = $referenceSelector->pick($character);
            $imageResult = $fal->generate($imageBuilt['prompt'], $imageBuilt['negative_prompt'], $reference);
            $baseImagePath = "generations/{$character->tenant_id}/{$character->id}/" . now()->format('Ymd_His') . '_' . Str::random(6) . '.png';
            Storage::disk('local')->put($baseImagePath, $imageResult['binary']);
            $this->saveCompanionReferenceIfNeeded($character, $post['scene'], $baseImagePath);
            $mediaPaths = $this->postType === 'carousel'
                ? $this->buildCarouselSlides($overlay, $character, $imageResult['binary'], $post['carousel_slides'])
                : [$baseImagePath];
            $timelineEntry = TimelineEntry::create([
                'character_id' => $character->id, 'life_event_id' => $lifeEvent->id,
                'topic' => $lifeEvent->scene_hint['topic'] ?? null, 'scene' => $post['scene'],
                'title' => $post['titolo'] ?? $lifeEvent->title,
            ]);
            $postRecord = Post::create([
                'character_id' => $character->id, 'generation_id' => $generation->id,
                'platform' => 'instagram', 'media_type' => $this->postType, 'status' => 'draft',
                'caption' => $this->buildFullCaption($post), 'media_urls' => $mediaPaths,
            ]);
            $generation->update([
                'status' => 'completed',
                'output' => [
                    'post' => $post, 'image_path' => $baseImagePath, 'media_paths' => $mediaPaths,
                    'seed' => $imageResult['seed'], 'reference_asset_id' => $reference->id,
                    'timeline_entry_id' => $timelineEntry->id, 'post_id' => $postRecord->id,
                    'news_used' => $news,
                ],
                'completed_at' => now(),
            ]);
            return $postRecord;
        } catch (Throwable $e) {
            $generation->update(['status' => 'failed', 'output' => ['error' => $e->getMessage()]]);
            throw $e;
        }
    }

    /**
     * Percorso riconciliato (12.1): la decisione (se e cosa pubblicare) arriva da
     * EditorialCycleService::run() — stessa logica già verificata da
     * character:run-editorial-cycle, pavimento max_giorni_silenzio incluso (11.12), non
     * duplicata qui. run() crea già generations/editorial_decisions/post draft (media_urls
     * vuoto); qui si genera solo l'immagine (riusando FalImageService/ReferenceImageSelector
     * esistenti) e si completa il post, oppure non si fa nulla se la decisione è
     * "non_pubblicare".
     */
    private function handleEditorialBrainFlow(
        Character $character, EditorialCycleService $editorialCycle,
        ReferenceImageSelector $referenceSelector, FalImageService $fal, ImageTextOverlayService $overlay,
        StoryComposerService $storyComposer
    ): ?Post {
        $result = $editorialCycle->run($character, $this->instructions);

        if (! $result['decision']) {
            throw new RuntimeException($result['generation']->output['error'] ?? 'Cervello editoriale fallito.');
        }

        if (! $result['post']) {
            return null; // non_pubblicare: esito legittimo, nessun post da creare.
        }

        try {
            // Nessun ramo dedicato per formato "reel" qui sotto: il cervello editoriale non può
            // più deciderlo (sez. 12.5, EditorialBrainService::formatoEnum) perché non esiste una
            // pipeline di generazione video, solo FalImageService (text-to-image). Se "reel"
            // arriva comunque fin qui (record storico, o formatoEnum toccato senza una vera
            // pipeline video pronta), ricade nell'else sotto e produce una singola immagine
            // statica come un post normale — comportamento invariato, non un fix.
            $isOggetto = $result['decision']['formato'] === 'oggetto';
            $isConversazione = $result['decision']['formato'] === 'conversazione';

            // Rinforzo il negative prompt sia per stagione (12.6) sia per "oggetto": più
            // affidabile di contare solo sull'istruzione positiva nel prompt_immagine (che il
            // cervello editoriale scrive da solo, quindi può comunque sbagliare).
            $negativePrompt = self::CERVELLO_NEGATIVE_PROMPT;
            $seasonalTerms = self::SEASONAL_NEGATIVE_TERMS[TemporalContext::season()] ?? [];
            if ($seasonalTerms) {
                $negativePrompt .= ', ' . implode(', ', $seasonalTerms);
            }
            if ($isOggetto) {
                $negativePrompt .= ', person, human, face, body, portrait';
            }

            // "conversazione" (documento nuovi formati, opzione A punto 2): mockup puro di
            // un'app di messaggistica, nessuna foto del personaggio per scelta di design — a
            // differenza di "oggetto"/"screenshot"/tutti gli altri formati, qui NON si passa mai
            // da FalImageService: nessuna reference da scegliere, nessun costo di generazione
            // immagine, solo rendering grafico da messaggi_conversazione (già validati da
            // EditorialBrainService::validate(), almeno 2). $reference resta null qui sotto,
            // stesso trattamento già scelto per "oggetto".
            if ($isConversazione) {
                $reference = null;
                $seed = null;
                $imageBinary = $overlay->renderConversationMockup($character->name, $result['decision']['messaggi_conversazione'] ?? []);
            } else {
                // "oggetto" usa un modello generico senza reference (generateStandalone, sezione 2.2):
                // generate() è legato a fal-ai/ideogram/character, pensato per PRESERVARE il personaggio
                // — l'opposto di quello che serve qui. $reference resta null in questo ramo, quindi
                // 'reference_asset_id' più sotto sarà null per i post "oggetto" (atteso, non un bug).
                if ($isOggetto) {
                    $reference = null;
                    $imageResult = $fal->generateStandalone($result['decision']['prompt_immagine'], $negativePrompt);
                } else {
                    $reference = $referenceSelector->pick($character);
                    if (! $reference) {
                        throw new RuntimeException("Nessun asset di riferimento trovato per {$character->name}.");
                    }
                    $imageResult = $fal->generate($result['decision']['prompt_immagine'], $negativePrompt, $reference);
                }
                $seed = $imageResult['seed'];
                $imageBinary = $imageResult['binary'];
            }

            // Formato "story": fino a qui l'immagine generata e' la stessa foto 4:5 di un post
            // normale. Componiamo in verticale 9:16 con StoryComposerService (stesso trattamento
            // gia' usato dall'altra pipeline story, GenerateNewsStoryJob), usando come testo la
            // caption della decisione del cervello editoriale ("testo") invece di un commento a
            // una notizia. Se "testo" e' vuoto, StoryComposerService produce comunque un canvas
            // verticale valido, solo senza testo sovrapposto.
            if ($result['decision']['formato'] === 'story') {
                $imageBinary = $storyComposer->compose($imageBinary, $result['decision']['testo'] ?? '');
            } elseif ($result['decision']['formato'] === 'post' && trim($result['decision']['testo_overlay'] ?? '') !== '') {
                // Immagine singola "nuda" (senza overlay) era il formato più debole nei dati di
                // mercato (analisi 21/07, sez. 2): stesso trattamento quote-card già usato per le
                // slide del carousel, ma con una sola frase (testo_overlay, distinta dalla caption
                // in "testo"). La guardia in EditorialBrainService::validate() dovrebbe già
                // impedire un testo_overlay vuoto qui, ma se mai capitasse (record storico, o
                // quella guardia rimossa in futuro) meglio degradare a post senza overlay che
                // rompere la generazione.
                $imageBinary = $overlay->overlay($imageBinary, $result['decision']['testo_overlay']);
            } elseif ($result['decision']['formato'] === 'screenshot') {
                // Screenshot di conversazione simulata (analisi 21/07, sez. 3 opzione A): stesso
                // principio di "story"/"post" sopra, un overlay diverso (bolle di chat) sulla
                // stessa immagine di base, nessuna generazione Fal.ai aggiuntiva.
                $imageBinary = $overlay->overlayChatBubbles($imageBinary, $result['decision']['messaggi_chat'] ?? []);
            }

            $imagePath = "generations/{$character->tenant_id}/{$character->id}/" . now()->format('Ymd_His') . '_' . Str::random(6) . '.png';
            Storage::disk('local')->put($imagePath, $imageBinary);

            $mediaPaths = $result['decision']['formato'] === 'carousel'
                ? $this->buildCarouselSlides($overlay, $character, $imageResult['binary'], $result['decision']['carousel_slides'])
                : [$imagePath];

            $timelineEntry = TimelineEntry::create([
                'character_id' => $character->id,
                'storyline_id' => $result['editorial_decision']->storyline_id,
                'title' => $result['decision']['idea'],
                'memorability' => $result['editorial_decision']->storyline?->importance,
            ]);

            $result['post']->update(['media_urls' => $mediaPaths]);

            $result['generation']->update(['output' => array_merge($result['generation']->output, [
                'image_path' => $imagePath,
                'media_paths' => $mediaPaths,
                'seed' => $seed, // null per "conversazione": nessuna generazione Fal.ai in quel ramo
                'reference_asset_id' => $reference?->id, // null per "oggetto"/"conversazione": nessuna reference selezionata in quei rami
                'timeline_entry_id' => $timelineEntry->id,
            ])]);
        } catch (Throwable $e) {
            $result['post']->update(['status' => 'failed']);
            throw $e;
        }

        return $result['post']->fresh();
    }

    private function buildCarouselSlides(ImageTextOverlayService $overlay, Character $character, string $baseImageBinary, array $slideTexts): array
    {
        $paths = [];
        foreach ($slideTexts as $index => $text) {
            $composited = $overlay->overlay($baseImageBinary, $text);
            $slidePath = "generations/{$character->tenant_id}/{$character->id}/" . now()->format('Ymd_His') . '_' . Str::random(6) . "_slide{$index}.png";
            Storage::disk('local')->put($slidePath, $composited);
            $paths[] = $slidePath;
        }
        return $paths;
    }
    private function buildFullCaption(array $post): string
    {
        $lines = [$post['caption'] ?? ''];
        if (! empty($post['hashtags'])) {
            $lines[] = implode(' ', array_map(fn ($h) => str_starts_with($h, '#') ? $h : "#{$h}", $post['hashtags']));
        }
        return trim(implode("\n\n", array_filter($lines)));
    }
    private function saveCompanionReferenceIfNeeded(Character $character, array $scene, string $imagePath): void
    {
        $companion = null;
        foreach ($scene['props'] ?? [] as $prop) {
            if (isset(self::COMPANION_MAP[$prop])) {
                $companion = self::COMPANION_MAP[$prop];
                break;
            }
        }
        if (! $companion) {
            return;
        }
        CharacterAsset::updateOrCreate(
            ['character_id' => $character->id, 'type' => "companion_{$companion}"],
            ['file_path' => $imagePath, 'is_default' => false]
        );
    }
}
