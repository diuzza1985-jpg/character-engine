<?php

namespace App\Livewire;

use App\Models\Character;
use App\Models\CharacterDraft;
use App\Services\ConvertCharacterDraftToCharacter;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * Wizard di creazione/modifica personaggio. Due modalità:
 * - anonima (/crea-personaggio, nessun account): bozza legata a session_token, come alla prima
 *   consegna.
 * - modifica (/personaggi/{character}/modifica, autenticato): riapre la STESSA draft collegata
 *   al Character (draft.character_id), precompilata — l'utente non tocca mai il prompt/prosa
 *   bible direttamente, solo il questionario strutturato (decisione esplicita, sostituisce il
 *   pannello con textarea sulla prosa della consegna precedente).
 * Le micro-interazioni (tile/chip/swatch/slider) sono Alpine puro lato client — Livewire entra
 * in gioco solo ai confini di step ("Avanti"), che passa i valori raccolti a nextStep() per
 * validazione server-side e persistenza su character_drafts (spec tecnica §1).
 */
class CharacterCreationWizard extends Component
{
    // Stessa lista di default già usata nella bible di Sofia per i limiti di sicurezza —
    // precompilata e non deselezionabile lato utente (solo aggiungibile), sia per le battute
    // sia, più in generale, per qualunque contenuto (questionario, sez. 4 e 7).
    private const DEFAULT_SAFE_TOPICS = ['Aspetto fisico', 'Salute / malattie', 'Salute mentale', 'Difficoltà economiche', 'Lutti e tragedie'];

    public string $step = 'intro';
    public ?string $draftId = null;
    public ?Character $editingCharacter = null;

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
    public ?string $hairLength = null;
    public ?string $hairStyle = null;
    public ?string $eyeColor = null;
    public ?string $bodyType = null;
    public ?string $noseDetail = null;
    public ?string $mouthDetail = null;
    public ?string $distinguishingDetail = null;

    // Approfondimento (solo utenti autenticati — spec tecnica del questionario, mai in scope
    // per il wizard anonimo)
    public array $dietaryHabits = [];
    public array $hobbies = [];
    public ?string $lifeGoals = null;
    public array $fears = [];
    public ?string $backstory = null;
    public array $keyRelationships = [];
    public array $typicalPhrases = [];
    public array $hyperSpecificDetails = [];

    private function steps(): array
    {
        // "Approfondimento" solo in modalità modifica (pannello di un personaggio già salvato),
        // mai in creazione — nemmeno per chi è già autenticato: è un arricchimento successivo,
        // non parte del primo giro (coerente con lo spec originale, "sbloccabile dopo
        // l'attivazione del personaggio", corretto qui dopo revisione: prima compariva anche
        // in creazione se autenticati, non era quello che si voleva).
        $steps = ['intro', 'why', 'identity', 'personality', 'voice', 'humor', 'appearance'];
        if ($this->editingCharacter) {
            $steps[] = 'approfondimento';
        }
        $steps[] = 'summary';

        return $steps;
    }

    /**
     * @param  Character|null  $character  Route model binding su /personaggi/{character}/modifica
     *   — se presente, entra in modalità modifica: carica la draft collegata invece che via
     *   session_token e salta lo screen "intro" (marketing per chi non ha ancora un account).
     */
    public function mount(?Character $character = null): void
    {
        if ($character) {
            // Un personaggio creato prima di questo sistema (es. Sofia, via admin Filament) non
            // ha nessuna draft collegata — aprire comunque il wizard mostrerebbe un questionario
            // vuoto che, salvato, sovrascriverebbe con contenuto generico la sua bible reale.
            // Meglio bloccare qui che rischiare di distruggere dati veri.
            abort_if(! $character->draft, 404, 'Questo personaggio non è stato creato con il questionario e non può essere modificato da qui.');

            $this->editingCharacter = $character;
            $this->hydrateFromDraft($character->draft);
            $this->step = 'why';

            return;
        }

        // Ripresa esplicita di una bozza specifica (link "Continua" dal menu personaggi,
        // ?bozza={id}) — necessaria da autenticati con più personaggi in lavorazione
        // contemporaneamente: il solo session_token non basta più a capire QUALE bozza
        // riprendere, e "l'ultima del tenant" sarebbe ambiguo se l'utente vuole invece iniziarne
        // una nuova. Verifica di appartenenza al tenant corrente prima di caricarla.
        $resumeId = request()->query('bozza');
        if ($resumeId && auth()->check()) {
            $draft = CharacterDraft::where('id', $resumeId)
                ->where('tenant_id', auth()->user()->tenant_id)
                ->whereNull('character_id')
                ->where('status', '!=', 'convertito')
                ->first();

            if ($draft) {
                $this->hydrateFromDraft($draft);

                return;
            }
        }

        $token = session('character_draft_token');
        if (! $token) {
            $token = (string) Str::uuid();
            session(['character_draft_token' => $token]);
        }

        $draft = CharacterDraft::where('session_token', $token)
            ->where('status', '!=', 'convertito')
            ->latest('created_at')
            ->first();

        if ($draft) {
            $this->hydrateFromDraft($draft);
        }
    }

    private function hydrateFromDraft(CharacterDraft $draft): void
    {
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
        $this->hairLength = $draft->hair_length;
        $this->hairStyle = $draft->hair_style;
        $this->eyeColor = $draft->eye_color;
        $this->bodyType = $draft->body_type;
        $this->noseDetail = $draft->nose_detail;
        $this->mouthDetail = $draft->mouth_detail;
        $this->distinguishingDetail = $draft->distinguishing_detail;
        $this->dietaryHabits = $draft->dietary_habits ?? [];
        $this->hobbies = $draft->hobbies ?? [];
        $this->lifeGoals = $draft->life_goals;
        $this->fears = $draft->fears ?? [];
        $this->backstory = $draft->backstory;
        $this->keyRelationships = $draft->key_relationships ?? [];
        $this->typicalPhrases = $draft->typical_phrases ?? [];
        $this->hyperSpecificDetails = $draft->hyper_specific_details ?? [];
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
            'approfondimento' => $this->applyApprofondimento($payload),
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
        $this->hairLength = $payload['hairLength'] ?? null;
        $this->hairStyle = $payload['hairStyle'] ?? null;
        $this->eyeColor = $payload['eyeColor'] ?? null;
        $this->bodyType = $payload['bodyType'] ?? null;
        $this->noseDetail = $payload['noseDetail'] ?? null;
        $this->mouthDetail = $payload['mouthDetail'] ?? null;
        $this->distinguishingDetail = $payload['distinguishingDetail'] ?? null;

        // hairLength è obbligatorio (a differenza di hairStyle, ora solo texture/acconciatura
        // facoltativa): la lunghezza dei capelli deve sempre finire nel profilo visivo, mai
        // lasciata implicita in una scelta di texture come "Ricci" (bug reale trovato indagando
        // l'incoerenza capelli di Sofia — vedi ConvertCharacterDraftToCharacter::hairDescription()).
        $this->validate([
            'ageRange' => ['required', 'string'],
            'presentation' => ['required', 'string'],
            'styleArchetype' => ['required', 'string'],
            'hairLength' => ['required', 'string'],
        ], [], ['ageRange' => 'fascia d\'età', 'presentation' => 'presentazione', 'styleArchetype' => 'stile', 'hairLength' => 'lunghezza capelli']);
    }

    /**
     * Tutto facoltativo (spec: "puoi farlo con calma dopo", non blocca l'attivazione del
     * personaggio) — nessuna validazione richiesta.
     */
    private function applyApprofondimento(array $payload): void
    {
        $this->dietaryHabits = $payload['dietaryHabits'] ?? [];
        $this->hobbies = $payload['hobbies'] ?? [];
        $this->lifeGoals = $payload['lifeGoals'] ?? null;
        $this->fears = $payload['fears'] ?? [];
        $this->backstory = $payload['backstory'] ?? null;
        $this->keyRelationships = $payload['keyRelationships'] ?? [];
        $this->typicalPhrases = $payload['typicalPhrases'] ?? [];
        $this->hyperSpecificDetails = $payload['hyperSpecificDetails'] ?? [];
    }

    /**
     * Unico CTA dello screen finale: niente bozze anonime "fluttuanti" senza proprietario
     * (decisione esplicita dell'utente, cambiata rispetto allo spec originale che permetteva un
     * "salva per dopo" senza account). Se non autenticato, si persiste comunque la bozza (così
     * non si perde nulla nel passaggio) ma si rimanda a login/registrazione spiegando che senza
     * account i dati non restano — la conversione vera in Character avviene solo lì (o subito
     * sotto, se l'utente è già loggato, incluso il caso "modifica" dove l'aggiornamento avviene
     * in-place sullo stesso Character grazie a draft.character_id già valorizzato).
     */
    public function saveAndContinue()
    {
        // Guardia server-side: saveAndContinue è un metodo Livewire pubblico, raggiungibile in
        // teoria anche senza essere passati per lo step "identity" (il client non va mai fidato
        // sul rispettare l'ordine degli step) — senza questo controllo ConvertCharacterDraftToCharacter
        // andrebbe in errore fatale su un nome nullo invece di un errore di validazione gestito.
        $this->validate([
            'goal' => ['required', 'string'],
            'niche' => ['required', 'array', 'min:2'],
            'name' => ['required', 'string'],
            'oneLiner' => ['required', 'string'],
        ], [], ['goal' => 'obiettivo', 'niche' => 'nicchia', 'name' => 'nome', 'oneLiner' => '"in una frase, chi è"']);

        $this->persistDraft();

        if (! auth()->check()) {
            // Non una flash: deve sopravvivere anche se l'utente passa da registrati ad accedi
            // (o viceversa) prima di completare — viene rimossa solo a conversione avvenuta.
            session(['save_requires_auth' => true]);

            return redirect()->route('register');
        }

        app(ConvertCharacterDraftToCharacter::class)->convert(
            CharacterDraft::findOrFail($this->draftId),
            auth()->user()->resolveOrCreateTenant()->id
        );

        return redirect()->route('character.panel');
    }

    private function persistDraft(array $extra = []): void
    {
        $attributes = array_merge([
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
            'hair_length' => $this->hairLength,
            'hair_style' => $this->hairStyle,
            'eye_color' => $this->eyeColor,
            'body_type' => $this->bodyType,
            'nose_detail' => $this->noseDetail,
            'mouth_detail' => $this->mouthDetail,
            'distinguishing_detail' => $this->distinguishingDetail,
            'dietary_habits' => $this->dietaryHabits,
            'hobbies' => $this->hobbies,
            'life_goals' => $this->lifeGoals,
            'fears' => $this->fears,
            'backstory' => $this->backstory,
            'key_relationships' => $this->keyRelationships,
            'typical_phrases' => $this->typicalPhrases,
            'hyper_specific_details' => $this->hyperSpecificDetails,
            'expires_at' => now()->addDays(30),
        ], $extra);

        // Da autenticato, la bozza appartiene sempre al tenant fin da subito (non solo alla
        // conversione finale) — così compare nel menu personaggi anche prima di finire il
        // questionario. resolveOrCreateTenant() gestisce anche account pre-esistenti senza
        // tenant (bug reale trovato in produzione, vedi User::resolveOrCreateTenant()).
        if (auth()->check()) {
            $attributes['tenant_id'] = auth()->user()->resolveOrCreateTenant()->id;
        }

        if ($this->draftId) {
            // Bozza già identificata (da mount(), che filtra esplicitamente status!=convertito,
            // o da un nextStep precedente in questo stesso giro) — sempre un aggiornamento.
            $draft = CharacterDraft::findOrFail($this->draftId);
            $draft->update($attributes);
        } else {
            // Prima volta in questo giro: crea SEMPRE una riga nuova. Un updateOrCreate per
            // session_token da solo riaggancerebbe la bozza già convertita di un personaggio
            // precedente creato nella stessa sessione browser (bug reale: un secondo
            // personaggio sovrascriveva il primo invece di crearne uno separato).
            $attributes['session_token'] = session('character_draft_token');
            $draft = CharacterDraft::create($attributes);
        }

        $this->draftId = $draft->id;
    }

    public function render()
    {
        $steps = $this->steps();
        $stepIndex = array_search($this->step, $steps, true);

        return view('livewire.character-creation-wizard', [
            'steps' => $steps,
            'stepIndex' => $stepIndex,
        ])->layout('layouts.wizard');
    }
}
