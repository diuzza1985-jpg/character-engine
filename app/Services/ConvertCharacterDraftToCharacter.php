<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterBibleSection;
use App\Models\CharacterDraft;
use App\Models\CharacterEditorialSettings;
use App\Models\CharacterRelationship;
use App\Models\CharacterVisualProfile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Trasforma una CharacterDraft in un Character reale — sia alla prima registrazione sia, da
 * loggato, ogni volta che il questionario viene riaperto e risalvato (draft.character_id
 * valorizzato): in quel caso aggiorna il Character esistente invece di crearne uno nuovo
 * (update-in-place), perché la bozza resta la fonte strutturata canonica per tutta la vita del
 * personaggio, non un artefatto usa-e-getta pre-registrazione. Genera testo prosa per le sezioni
 * bible a partire dai campi strutturati — nessun refactor di EditorialContextBuilder per leggere
 * dati strutturati (fuori scope, spec tecnica §7): più veloce e meno rischioso produrre qui la
 * stessa forma di prosa che il motore già si aspetta (stesso stile della bible di Sofia).
 */
class ConvertCharacterDraftToCharacter
{
    public function convert(CharacterDraft $draft, int $tenantId): Character
    {
        $character = $draft->character_id
            ? Character::findOrFail($draft->character_id)
            : new Character();

        $character->tenant_id = $tenantId;
        $character->name = $draft->name;
        $character->one_liner = $draft->one_liner;
        if (! $character->exists) {
            $character->slug = $this->uniqueSlug($draft->name);
            $character->status = 'draft';
        }
        $character->save();

        // firstOrNew, non updateOrCreate: max_giorni_silenzio/diario_ogni_n_post sono tarabili
        // solo da admin Filament, non fanno parte del questionario — un riepilogo (update-in-place)
        // non deve mai riportarli ai default se erano già stati personalizzati.
        $settings = CharacterEditorialSettings::firstOrNew(['character_id' => $character->id]);
        $isNewSettings = ! $settings->exists;
        if ($isNewSettings) {
            $settings->max_giorni_silenzio = 2;
            $settings->diario_ogni_n_post = 20;
        }
        $settings->goal = $draft->goal;
        $settings->goal_secondary = $draft->goal_secondary;
        $settings->target_audience = $draft->target_audience;
        $settings->niche = $draft->niche;
        $settings->save();

        // Suggerite una volta sola, alla primissima creazione — mai risovrascritte su un
        // riepilogo/update-in-place successivo, altrimenti un admin che le ha corrette a mano in
        // Filament (checkpoint umano, documento temi-attualità sez. 3.C) se le vedrebbe rimpiazzate
        // silenziosamente ogni volta che l'utente riapre e risalva il questionario.
        // Entrambe le suggestioni vengono generate, non solo quella attiva oggi (YouTube/parole
        // chiave): le subreddit restano dormienti finché non arriva l'approvazione OAuth Reddit,
        // ma vengono comunque popolate ora così non serve un backfill quando quel giorno arriva —
        // nessun lavoro sprecato, solo non ancora usato.
        if ($isNewSettings) {
            $suggestedSubreddits = $this->suggestHotTopicSubreddits($draft);
            $suggestedKeywords = $this->suggestHotTopicKeywords($draft);
            $settings->update(array_filter([
                'hot_topic_subreddits' => $suggestedSubreddits ?: null,
                'hot_topic_keywords' => $suggestedKeywords ?: null,
            ]));
        }

        CharacterVisualProfile::updateOrCreate(
            ['character_id' => $character->id],
            [
                'age_description' => $this->ageDescription($draft),
                'face_description' => $this->faceDescription($draft),
                'hair_description' => $this->hairDescription($draft),
                'wardrobe_notes' => $draft->style_archetype,
                'visual_rules_text' => $draft->distinguishing_detail,
            ]
        );

        foreach ($this->bibleSections($draft) as $sectionKey => $content) {
            $section = CharacterBibleSection::firstOrNew([
                'character_id' => $character->id,
                'section_key' => $sectionKey,
            ]);
            $section->content = $content;
            $section->version = ($section->version ?? 0) + 1;
            $section->save();
        }

        $this->syncKeyRelationships($character, $draft);

        $draft->update(['status' => 'convertito', 'tenant_id' => $tenantId, 'character_id' => $character->id]);

        return $character;
    }

    /**
     * "Relazioni chiave" dell'Approfondimento (nome/relazione/tratto) alimentano
     * CharacterRelationship — lo stesso modello narrativo già popolato dal motore di vita
     * (CharacterLiveTick), finora mai da input utente diretto.
     */
    private function syncKeyRelationships(Character $character, CharacterDraft $draft): void
    {
        foreach ($draft->key_relationships ?? [] as $relazione) {
            $nome = trim($relazione['nome'] ?? '');
            if ($nome === '') {
                continue;
            }

            CharacterRelationship::updateOrCreate(
                ['character_id' => $character->id, 'name' => $nome],
                ['type' => $relazione['relazione'] ?? null, 'notes' => $relazione['tratto'] ?? null]
            );
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'personaggio';
        $slug = $base;
        $suffix = 1;
        while (Character::where('slug', $slug)->exists()) {
            $slug = "{$base}-" . ++$suffix;
        }

        return $slug;
    }

    private function ageDescription(CharacterDraft $draft): string
    {
        return trim("Fascia d'età {$draft->age_range}. Presentazione: {$draft->presentation}.");
    }

    private function faceDescription(CharacterDraft $draft): string
    {
        $parts = array_filter([
            $draft->eye_color ? "occhi {$draft->eye_color}" : null,
            $draft->nose_detail ? "naso {$draft->nose_detail}" : null,
            $draft->mouth_detail ? "bocca {$draft->mouth_detail}" : null,
            $draft->body_type ? "corporatura {$draft->body_type}" : null,
        ]);

        return $parts ? ucfirst(implode(', ', $parts)) . '.' : 'Non specificato.';
    }

    /**
     * hair_length è sempre incluso quando presente, indipendentemente da cosa l'utente ha scelto
     * (o non scelto) su hair_style/texture — bug reale trovato indagando l'incoerenza capelli di
     * Sofia: un profilo visivo che descrive solo colore e texture ("castano, ricci") lascia la
     * lunghezza completamente libera al generatore di immagini, causando variazioni vistose tra
     * una foto e l'altra.
     */
    private function hairDescription(CharacterDraft $draft): string
    {
        $parts = array_filter([$draft->hair_color, $draft->hair_length, $draft->hair_style]);

        return $parts ? ucfirst(implode(', ', $parts)) . '.' : 'Non specificato.';
    }

    /**
     * Suggerisce 3-6 subreddit pertinenti alla nicchia/interessi del personaggio, usate poi da
     * character:check-hot-topics per il formato "tema_caldo" (documento temi-attualità, sez. 3).
     * Deliberatamente best-effort: nessuna verifica che le subreddit esistano davvero su Reddit
     * o siano scritte correttamente — è compito dell'admin rivederle/correggerle in Filament
     * prima che vengano usate in produzione (checkpoint umano, stesso principio già scritto per
     * le reference delle relazioni in character-engine-coerenza-visiva-relazioni.md sez. 3). Se
     * la chiamata fallisce, il personaggio resta comunque creato senza subreddit suggerite —
     * l'admin le aggiunge a mano quando vuole, non è un blocco alla creazione.
     *
     * @return array<int, string>
     */
    private function suggestHotTopicSubreddits(CharacterDraft $draft): array
    {
        $niche = implode(', ', $draft->niche ?? []);
        $hobbies = implode(', ', $draft->hobbies ?? []);
        $goal = $draft->goal ?? '';

        if ($niche === '' && $hobbies === '') {
            return [];
        }

        $prompt = <<<TXT
Suggerisci un piccolo elenco di subreddit di Reddit (in inglese, comunità reali e plausibili — solo il nome, senza "r/" davanti) pertinenti agli interessi e alla nicchia di un personaggio con questo profilo:
- Nicchia/obiettivo: {$goal} — {$niche}
- Hobby/interessi: {$hobbies}

Servono per intercettare discussioni "calde" del momento pertinenti a questi argomenti. Scegli comunità generaliste e conosciute sull'argomento, non nicchie tecniche troppo specifiche dove sarebbe raro trovare un tema abbastanza generale da motivare un post pubblico.

Restituisci un JSON con un solo campo "subreddits": un array di 3-6 stringhe (solo il nome della subreddit, minuscolo, senza spazi né "r/").
TXT;

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.text_model'),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'suggerimento_subreddit',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['subreddits'],
                                'properties' => [
                                    'subreddits' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('ConvertCharacterDraftToCharacter: suggerimento subreddit fallito', ['draft_id' => $draft->id, 'status' => $response->status()]);
                return [];
            }

            $content = $response->json('choices.0.message.content');
            $data = $content ? json_decode($content, true) : null;

            return is_array($data['subreddits'] ?? null) ? array_values(array_filter($data['subreddits'])) : [];
        } catch (Throwable $e) {
            Log::warning('ConvertCharacterDraftToCharacter: eccezione nel suggerimento subreddit, proseguo senza', ['draft_id' => $draft->id, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Suggerisce 3-6 parole chiave pertinenti alla nicchia/interessi del personaggio, usate da
     * character:check-hot-topics per il match contro il pool condiviso di video di tendenza
     * YouTube (fonte attiva per "tema_caldo" — sostituisce Reddit finché quello resta dormiente,
     * non un secondo formato separato). Stesso principio di suggestHotTopicSubreddits(): best-
     * effort, checkpoint umano in Filament prima dell'uso in produzione, nessun blocco alla
     * creazione se la chiamata fallisce.
     *
     * @return array<int, string>
     */
    private function suggestHotTopicKeywords(CharacterDraft $draft): array
    {
        $niche = implode(', ', $draft->niche ?? []);
        $hobbies = implode(', ', $draft->hobbies ?? []);
        $goal = $draft->goal ?? '';

        if ($niche === '' && $hobbies === '') {
            return [];
        }

        $prompt = <<<TXT
Suggerisci un piccolo elenco di parole chiave (in italiano, 1-3 parole ciascuna) pertinenti agli interessi e alla nicchia di un personaggio con questo profilo:
- Nicchia/obiettivo: {$goal} — {$niche}
- Hobby/interessi: {$hobbies}

Servono per riconoscere, tra i titoli e le descrizioni di video di tendenza su YouTube, quali sono pertinenti a questo personaggio — devono essere termini che comparirebbero davvero in un titolo di video sull'argomento, non categorie astratte troppo generiche (es. "intelligenza artificiale" va bene, "tecnologia" da sola è troppo vago).

Restituisci un JSON con un solo campo "keywords": un array di 3-6 stringhe.
TXT;

        try {
            $response = Http::withToken(config('services.openai.key'))
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.text_model'),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'suggerimento_keyword',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'additionalProperties' => false,
                                'required' => ['keywords'],
                                'properties' => [
                                    'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                                ],
                            ],
                        ],
                    ],
                ]);

            if ($response->failed()) {
                Log::warning('ConvertCharacterDraftToCharacter: suggerimento keyword tema caldo fallito', ['draft_id' => $draft->id, 'status' => $response->status()]);
                return [];
            }

            $content = $response->json('choices.0.message.content');
            $data = $content ? json_decode($content, true) : null;

            return is_array($data['keywords'] ?? null) ? array_values(array_filter($data['keywords'])) : [];
        } catch (Throwable $e) {
            Log::warning('ConvertCharacterDraftToCharacter: eccezione nel suggerimento keyword tema caldo, proseguo senza', ['draft_id' => $draft->id, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * @return array<string, string> section_key => content prosa
     */
    private function bibleSections(CharacterDraft $draft): array
    {
        return [
            'valori' => $this->valoriSection($draft),
            'voce' => $this->voceSection($draft),
            'umorismo' => $this->umorismoSection($draft),
            'famiglia' => $this->famigliaSection($draft),
            'regole' => $this->regoleSection($draft),
            'biografia' => $this->biografiaSection($draft),
        ];
    }

    private function valoriSection(CharacterDraft $draft): string
    {
        $traits = implode(', ', $draft->traits ?? []);
        $values = implode(', ', $draft->core_values ?? []);
        $dislikes = implode(', ', $draft->dislikes ?? []);

        return <<<TXT
# Temperamento

{$draft->name} è {$traits}.

# Valori

Per {$draft->name} sono importanti: {$values}.

# Cose che non sopporta

{$draft->name} non sopporta: {$dislikes}.
TXT;
    }

    private function voceSection(CharacterDraft $draft): string
    {
        $formality = $this->scaleLabel($draft->communication_formality, 'molto formale', 'equilibrato tra formale e informale', 'molto informale');
        $verbosity = $this->scaleLabel($draft->communication_verbosity, 'molto conciso', 'equilibrato tra conciso ed espansivo', 'molto espansivo');
        $directness = $this->scaleLabel($draft->communication_directness, 'molto diretto', 'equilibrato tra diretto e diplomatico', 'molto diplomatico');
        $emoji = match ($draft->emoji_usage) {
            'mai' => 'Non usa mai emoji.',
            'spesso' => 'Usa spesso le emoji.',
            default => 'Usa le emoji raramente.',
        };

        return <<<TXT
# Tono e voce

{$draft->name} è {$formality}, {$verbosity}, {$directness}. {$emoji}
TXT;
    }

    private function umorismoSection(CharacterDraft $draft): string
    {
        if ($draft->humor_level === 'mai') {
            return <<<TXT
# Umorismo

{$draft->name} è un personaggio serio, non usa mai umorismo nei suoi contenuti.
TXT;
        }

        $intensita = match ($draft->humor_level) {
            'forte' => 'è uno dei suoi tratti più forti',
            'leggero' => 'è presente con qualche tocco leggero, non è il suo tratto principale',
            default => 'compare raramente, solo in occasioni particolari',
        };
        $targets = implode(', ', $draft->joke_targets ?? []);
        $safeTopics = implode(', ', $draft->humor_safe_topics ?? []);

        return <<<TXT
# Umorismo

L'umorismo di {$draft->name} {$intensita}.

## I suoi bersagli preferiti

{$draft->name} scherza spesso su: {$targets}.

## Cose su cui non scherza mai

Mai: {$safeTopics}.

L'umorismo non deve mai ferire chi sta già soffrendo. Prima di scegliere una battuta o un
bersaglio comico, controllare cosa è già stato raccontato di recente per non ripetersi.
TXT;
    }

    private function famigliaSection(CharacterDraft $draft): string
    {
        $pets = ! empty($draft->pets) ? implode(', ', $draft->pets) : 'nessun animale domestico';

        return <<<TXT
# Vita quotidiana

{$draft->name} vive: {$draft->living_situation}. Ambiente: {$draft->environment}. Animali domestici: {$pets}.
TXT;
    }

    private function regoleSection(CharacterDraft $draft): string
    {
        $limits = implode(', ', $draft->content_safe_limits ?? []);

        return <<<TXT
# Limiti di sicurezza sui contenuti

{$draft->name} non affronta mai in nessun contenuto: {$limits}.
TXT;
    }

    /**
     * Da "Approfondimento" (solo utenti autenticati, spec tecnica del questionario §
     * Approfondimento) — sezione bible mai usata finora ("biografia", 16° section_key).
     * Ogni blocco è facoltativo: un personaggio senza Approfondimento compilato ottiene
     * comunque una sezione valida, non vuota.
     */
    private function biografiaSection(CharacterDraft $draft): string
    {
        $parts = [];

        if ($draft->backstory) {
            $parts[] = "# Storia personale\n\n{$draft->backstory}";
        }
        if ($draft->life_goals) {
            $parts[] = "# Sogni e obiettivi\n\n{$draft->life_goals}";
        }
        if (! empty($draft->fears)) {
            $parts[] = '# Paure e insicurezze' . "\n\n" . implode(', ', $draft->fears) . '.';
        }
        if (! empty($draft->hobbies)) {
            $parts[] = '# Hobby' . "\n\n" . implode(', ', $draft->hobbies) . '.';
        }
        if (! empty($draft->dietary_habits)) {
            $parts[] = '# Abitudini alimentari' . "\n\n" . implode(', ', $draft->dietary_habits) . '.';
        }
        if (! empty($draft->typical_phrases)) {
            $phrases = implode("\n", array_map(fn ($p) => "- \"{$p}\"", $draft->typical_phrases));
            $parts[] = "# Frasi tipiche\n\n{$phrases}";
        }
        if (! empty($draft->hyper_specific_details)) {
            $parts[] = '# Dettagli iper-specifici' . "\n\n" . implode(', ', $draft->hyper_specific_details) . '.';
        }

        return $parts
            ? implode("\n\n", $parts)
            : "# Storia personale\n\nNessun dettaglio di approfondimento fornito ancora.";
    }

    private function scaleLabel(?int $value, string $low, string $mid, string $high): string
    {
        $value ??= 50;
        if ($value <= 30) {
            return $low;
        }
        if ($value >= 71) {
            return $high;
        }

        return $mid;
    }
}
