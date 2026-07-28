<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterBibleSection;
use App\Models\CharacterDraft;
use App\Models\CharacterEditorialSettings;
use App\Models\CharacterRelationship;
use App\Models\CharacterVisualProfile;
use Illuminate\Support\Str;

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
        if (! $settings->exists) {
            $settings->max_giorni_silenzio = 2;
            $settings->diario_ogni_n_post = 20;
        }
        $settings->goal = $draft->goal;
        $settings->goal_secondary = $draft->goal_secondary;
        $settings->target_audience = $draft->target_audience;
        $settings->niche = $draft->niche;
        $settings->save();

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
