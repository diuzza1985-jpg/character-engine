<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CharacterBibleSection;
use App\Models\CharacterDraft;
use App\Models\CharacterEditorialSettings;
use App\Models\CharacterVisualProfile;
use Illuminate\Support\Str;

/**
 * Trasforma una CharacterDraft (questionario pubblico, utente anonimo) in un Character reale,
 * al momento della registrazione. Genera testo prosa per le sezioni bible a partire dai campi
 * strutturati raccolti nel wizard — nessun refactor di EditorialContextBuilder per leggere dati
 * strutturati (fuori scope, spec tecnica §7): più veloce e meno rischioso produrre qui la stessa
 * forma di prosa che il motore già si aspetta (stesso stile della bible di Sofia).
 */
class ConvertCharacterDraftToCharacter
{
    public function convert(CharacterDraft $draft, int $tenantId): Character
    {
        $character = Character::create([
            'tenant_id' => $tenantId,
            'name' => $draft->name,
            'one_liner' => $draft->one_liner,
            'slug' => $this->uniqueSlug($draft->name),
            'status' => 'draft',
        ]);

        CharacterEditorialSettings::create([
            'character_id' => $character->id,
            'max_giorni_silenzio' => 2,
            'diario_ogni_n_post' => 20,
            'goal' => $draft->goal,
            'goal_secondary' => $draft->goal_secondary,
            'target_audience' => $draft->target_audience,
            'niche' => $draft->niche,
        ]);

        CharacterVisualProfile::create([
            'character_id' => $character->id,
            'age_description' => $this->ageDescription($draft),
            'face_description' => $this->faceDescription($draft),
            'hair_description' => $this->hairDescription($draft),
            'wardrobe_notes' => $draft->style_archetype,
            'visual_rules_text' => $draft->distinguishing_detail,
        ]);

        foreach ($this->bibleSections($draft) as $sectionKey => $content) {
            CharacterBibleSection::create([
                'character_id' => $character->id,
                'section_key' => $sectionKey,
                'content' => $content,
                'version' => 1,
            ]);
        }

        $draft->update(['status' => 'convertito', 'tenant_id' => $tenantId]);

        return $character;
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

    private function hairDescription(CharacterDraft $draft): string
    {
        $parts = array_filter([$draft->hair_color, $draft->hair_style]);

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
