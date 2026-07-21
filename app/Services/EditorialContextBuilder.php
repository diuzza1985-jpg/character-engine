<?php
namespace App\Services;
use App\Models\Character;
use App\Models\CharacterRelationship;
use App\Models\Post;
use App\Models\Storyline;
use App\Models\TimelineEntry;
use App\Support\TemporalContext;
use Illuminate\Support\Str;

/**
 * Assembla in una struttura unica tutto ciò che serve al cervello editoriale (11.7) per decidere
 * cosa pubblicare oggi. Puramente deterministico: nessuna chiamata esterna, solo lettura DB.
 */
class EditorialContextBuilder
{
    private const MAX_STORYLINES = 3;
    private const MAX_RELATIONSHIPS = 5;
    private const MAX_RECENT_POSTS = 12;
    private const MAX_RECENT_TIMELINE = 15;

    public function build(Character $character): array
    {
        return [
            'personaggio' => [
                'nome' => $character->name,
                'slug' => $character->slug,
            ],
            'oggi' => [
                'data' => now()->toDateString(),
                'ora' => now()->format('H:i'),
                'momento_del_giorno' => TemporalContext::timeOfDay(),
                'stagione' => TemporalContext::season(),
            ],
            'bible' => $this->buildBible($character),
            'profilo_visivo' => $this->buildVisualProfile($character),
            'stato_interno' => $this->buildInternalState($character),
            'storyline_rilevanti' => $this->buildRelevantStorylines($character),
            'relazioni_rilevanti' => $this->buildRelevantRelationships($character),
            'contenuti_recenti' => $this->buildRecentPosts($character),
            'vita_recente' => $this->buildRecentTimeline($character),
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
            ->orderByDesc('last_interaction_at')
            ->take(self::MAX_RELATIONSHIPS)
            ->get()
            ->map(fn (CharacterRelationship $r) => [
                'nome' => $r->name,
                'tipo' => $r->type,
                'sentiment' => round($r->sentiment, 2),
                'ultima_interazione' => $r->last_interaction_at?->toDateString(),
            ])
            ->all();
    }

    private function buildRecentPosts(Character $character): array
    {
        return $character->posts()
            ->latest('created_at')
            ->take(self::MAX_RECENT_POSTS)
            ->get()
            ->map(fn (Post $p) => [
                'data' => $p->created_at->toDateString(),
                'formato' => $p->narrative_format ?? $p->media_type,
                'stato' => $p->status,
                'estratto_caption' => $p->caption ? Str::limit($p->caption, 120) : null,
            ])
            ->all();
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
}
