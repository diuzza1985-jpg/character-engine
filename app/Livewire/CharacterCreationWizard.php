<?php

namespace App\Livewire;

use App\Models\CharacterDraft;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Wizard pubblico di creazione personaggio (/crea-personaggio), nessun account richiesto.
 * Le micro-interazioni (tile/chip/swatch/slider) sono Alpine puro lato client — Livewire entra
 * in gioco solo ai confini di step ("Avanti"), che passa i valori raccolti a nextStep() per
 * validazione server-side e persistenza su character_drafts (spec tecnica §1).
 */
class CharacterCreationWizard extends Component
{
    private const STEPS = ['intro', 'why', 'identity', 'personality', 'voice', 'humor', 'appearance', 'summary'];

    // Stessa lista di default già usata nella bible di Sofia per i limiti di sicurezza —
    // precompilata e non deselezionabile lato utente (solo aggiungibile), sia per le battute
    // sia, più in generale, per qualunque contenuto (questionario, sez. 4 e 7).
    private const DEFAULT_SAFE_TOPICS = ['Aspetto fisico', 'Salute / malattie', 'Salute mentale', 'Difficoltà economiche', 'Lutti e tragedie'];

    public string $step = 'intro';
    public ?string $draftId = null;

    // Perché esiste
    public ?string $goal = null;
    public ?string $goalSecondary = null;
    public array $targetAudience = [];
    public array $niche = [];

    // Identità + vita quotidiana
    public ?string $name = null;
    public ?string $role = null;
    public ?string $oneLiner = null;
    public ?string $livingSituation = null;
    public array $pets = [];
    public ?string $environment = null;

    // Temperamento e valori
    public array $traits = [];
    public array $coreValues = [];
    public array $dislikes = [];

    // Come comunica
    public int $communicationFormality = 50;
    public int $communicationVerbosity = 50;
    public int $communicationDirectness = 50;
    public ?string $emojiUsage = null;

    // Umorismo
    public ?string $humorLevel = null;
    public array $jokeTargets = [];
    public array $humorSafeTopics = self::DEFAULT_SAFE_TOPICS;

    // Aspetto
    public ?string $ageRange = null;
    public ?string $presentation = null;
    public ?string $styleArchetype = null;
    public ?string $hairColor = null;
    public ?string $hairStyle = null;
    public ?string $eyeColor = null;
    public ?string $bodyType = null;
    public ?string $noseDetail = null;
    public ?string $mouthDetail = null;
    public ?string $distinguishingDetail = null;

    public function mount(): void
    {
        $token = session('character_draft_token');
        if (! $token) {
            $token = (string) Str::uuid();
            session(['character_draft_token' => $token]);
        }

        $draft = CharacterDraft::where('session_token', $token)
            ->where('status', '!=', 'convertito')
            ->latest('created_at')
            ->first();

        if (! $draft) {
            return;
        }

        $this->draftId = $draft->id;
        $this->goal = $draft->goal;
        $this->goalSecondary = $draft->goal_secondary;
        $this->targetAudience = $draft->target_audience ?? [];
        $this->niche = $draft->niche ?? [];
        $this->name = $draft->name;
        $this->role = $draft->role;
        $this->oneLiner = $draft->one_liner;
        $this->livingSituation = $draft->living_situation;
        $this->pets = $draft->pets ?? [];
        $this->environment = $draft->environment;
        $this->traits = $draft->traits ?? [];
        $this->coreValues = $draft->core_values ?? [];
        $this->dislikes = $draft->dislikes ?? [];
        $this->communicationFormality = $draft->communication_formality ?? 50;
        $this->communicationVerbosity = $draft->communication_verbosity ?? 50;
        $this->communicationDirectness = $draft->communication_directness ?? 50;
        $this->emojiUsage = $draft->emoji_usage;
        $this->humorLevel = $draft->humor_level;
        $this->jokeTargets = $draft->joke_targets ?? [];
        $this->humorSafeTopics = $draft->humor_safe_topics ?? self::DEFAULT_SAFE_TOPICS;
        $this->ageRange = $draft->age_range;
        $this->presentation = $draft->presentation;
        $this->styleArchetype = $draft->style_archetype;
        $this->hairColor = $draft->hair_color;
        $this->hairStyle = $draft->hair_style;
        $this->eyeColor = $draft->eye_color;
        $this->bodyType = $draft->body_type;
        $this->noseDetail = $draft->nose_detail;
        $this->mouthDetail = $draft->mouth_detail;
        $this->distinguishingDetail = $draft->distinguishing_detail;
    }

    public function goBack(string $to): void
    {
        // Nessuna validazione tornando indietro: i dati sono già persistiti dallo step
        // completato in precedenza, qui si cambia solo la schermata visibile.
        $this->step = $to;
    }

    /**
     * Unico punto di ingresso per ogni "Avanti": valida i dati dello step corrente, li assegna
     * alle property, persiste la bozza e avanza. $payload arriva da $wire.nextStep(...) lato
     * Alpine con solo i campi raccolti in quello step (spec tecnica §1: Alpine → Livewire solo
     * ai confini di step).
     */
    public function nextStep(string $from, string $to, array $payload): void
    {
        match ($from) {
            'why' => $this->applyWhy($payload),
            'identity' => $this->applyIdentity($payload),
            'personality' => $this->applyPersonality($payload),
            'voice' => $this->applyVoice($payload),
            'humor' => $this->applyHumor($payload),
            'appearance' => $this->applyAppearance($payload),
            default => null,
        };

        $this->persistDraft();
        $this->step = $to;
    }

    private function applyWhy(array $payload): void
    {
        $this->goal = $payload['goal'] ?? null;
        $this->goalSecondary = $payload['goalSecondary'] ?? null;
        $this->targetAudience = $payload['targetAudience'] ?? [];
        $this->niche = $payload['niche'] ?? [];

        $this->validate([
            'goal' => ['required', 'string'],
            'niche' => ['required', 'array', 'min:2'],
        ], [], [
            'goal' => 'obiettivo',
            'niche' => 'nicchia',
        ]);
    }

    private function applyIdentity(array $payload): void
    {
        $this->name = $payload['name'] ?? null;
        $this->role = $payload['role'] ?? null;
        $this->oneLiner = $payload['oneLiner'] ?? null;
        $this->livingSituation = $payload['livingSituation'] ?? null;
        $this->pets = $payload['pets'] ?? [];
        $this->environment = $payload['environment'] ?? null;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'oneLiner' => ['required', 'string', 'max:120'],
        ], [], [
            'name' => 'nome',
            'oneLiner' => '"in una frase, chi è"',
        ]);
    }

    private function applyPersonality(array $payload): void
    {
        $this->traits = $payload['traits'] ?? [];
        $this->coreValues = $payload['coreValues'] ?? [];
        $this->dislikes = $payload['dislikes'] ?? [];

        $this->validate([
            'traits' => ['required', 'array', 'min:4'],
            'coreValues' => ['required', 'array', 'min:2'],
            'dislikes' => ['required', 'array', 'min:2'],
        ], [], [
            'traits' => 'tratti',
            'coreValues' => 'valori',
            'dislikes' => 'antipatie',
        ]);
    }

    private function applyVoice(array $payload): void
    {
        $this->communicationFormality = (int) ($payload['communicationFormality'] ?? 50);
        $this->communicationVerbosity = (int) ($payload['communicationVerbosity'] ?? 50);
        $this->communicationDirectness = (int) ($payload['communicationDirectness'] ?? 50);
        $this->emojiUsage = $payload['emojiUsage'] ?? null;

        $this->validate([
            'emojiUsage' => ['required', 'string'],
        ], [], ['emojiUsage' => 'uso delle emoji']);
    }

    private function applyHumor(array $payload): void
    {
        $this->humorLevel = $payload['humorLevel'] ?? null;
        $this->jokeTargets = $payload['jokeTargets'] ?? [];
        $this->humorSafeTopics = $payload['humorSafeTopics'] ?? self::DEFAULT_SAFE_TOPICS;

        $rules = ['humorLevel' => ['required', 'string', 'in:mai,raramente,leggero,forte']];
        if ($this->humorLevel !== 'mai') {
            $rules['jokeTargets'] = ['required', 'array', 'min:5'];
        }
        $this->validate($rules, [], ['humorLevel' => 'livello di umorismo', 'jokeTargets' => 'bersagli comici']);

        // Il client non può togliere i default di sicurezza (solo aggiungerne) — non ci si fida
        // comunque del solo comportamento del client, si ripristina qui l'insieme minimo se
        // per qualunque motivo mancasse (stesso principio di EditorialBrainService::validate()
        // per i carousel_slides: meglio una guardia server-side esplicita).
        $this->humorSafeTopics = array_values(array_unique([...self::DEFAULT_SAFE_TOPICS, ...$this->humorSafeTopics]));
    }

    private function applyAppearance(array $payload): void
    {
        $this->ageRange = $payload['ageRange'] ?? null;
        $this->presentation = $payload['presentation'] ?? null;
        $this->styleArchetype = $payload['styleArchetype'] ?? null;
        $this->hairColor = $payload['hairColor'] ?? null;
        $this->hairStyle = $payload['hairStyle'] ?? null;
        $this->eyeColor = $payload['eyeColor'] ?? null;
        $this->bodyType = $payload['bodyType'] ?? null;
        $this->noseDetail = $payload['noseDetail'] ?? null;
        $this->mouthDetail = $payload['mouthDetail'] ?? null;
        $this->distinguishingDetail = $payload['distinguishingDetail'] ?? null;

        $this->validate([
            'ageRange' => ['required', 'string'],
            'presentation' => ['required', 'string'],
            'styleArchetype' => ['required', 'string'],
        ], [], ['ageRange' => 'fascia d\'età', 'presentation' => 'presentazione', 'styleArchetype' => 'stile']);
    }

    /**
     * "Salva per dopo" (spec tecnica §5): nessuna registrazione richiesta, la bozza resta
     * riprendibile con lo stesso session_token. Non è "Avanti" quindi non passa da nextStep().
     */
    public function saveForLater(): void
    {
        $this->persistDraft(['status' => 'completato']);
        session()->flash('draft_saved', true);
    }

    private function persistDraft(array $extra = []): void
    {
        $draft = CharacterDraft::updateOrCreate(
            ['session_token' => session('character_draft_token')],
            array_merge([
                'goal' => $this->goal,
                'goal_secondary' => $this->goalSecondary,
                'target_audience' => $this->targetAudience,
                'niche' => $this->niche,
                'name' => $this->name,
                'role' => $this->role,
                'one_liner' => $this->oneLiner,
                'living_situation' => $this->livingSituation,
                'pets' => $this->pets,
                'environment' => $this->environment,
                'traits' => $this->traits,
                'core_values' => $this->coreValues,
                'dislikes' => $this->dislikes,
                'communication_formality' => $this->communicationFormality,
                'communication_verbosity' => $this->communicationVerbosity,
                'communication_directness' => $this->communicationDirectness,
                'emoji_usage' => $this->emojiUsage,
                'humor_level' => $this->humorLevel,
                'joke_targets' => $this->jokeTargets,
                'humor_safe_topics' => $this->humorSafeTopics,
                'content_safe_limits' => self::DEFAULT_SAFE_TOPICS,
                'age_range' => $this->ageRange,
                'presentation' => $this->presentation,
                'style_archetype' => $this->styleArchetype,
                'hair_color' => $this->hairColor,
                'hair_style' => $this->hairStyle,
                'eye_color' => $this->eyeColor,
                'body_type' => $this->bodyType,
                'nose_detail' => $this->noseDetail,
                'mouth_detail' => $this->mouthDetail,
                'distinguishing_detail' => $this->distinguishingDetail,
                'expires_at' => now()->addDays(30),
            ], $extra)
        );

        $this->draftId = $draft->id;
    }

    public function render()
    {
        $stepIndex = array_search($this->step, self::STEPS, true);

        return view('livewire.character-creation-wizard', [
            'steps' => self::STEPS,
            'stepIndex' => $stepIndex,
        ])->layout('layouts.wizard');
    }
}
