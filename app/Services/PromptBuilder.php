<?php
namespace App\Services;
use App\Models\Character;
use App\Models\LifeEvent;
use App\Models\SceneElement;
use App\Models\TimelineEntry;
use App\Support\TemporalContext;
class PromptBuilder
{
    public function buildInstagramPostPrompt(Character $character, LifeEvent $lifeEvent, string $postType = 'image', array $newsDigest = [], ?string $instructions = null): string
    {
        $documentation = $this->buildDocumentation($character, 'instagram_post');
        $lifeMoment = $this->buildLifeMoment($character, $lifeEvent);
        $lifeContext = json_encode($lifeMoment, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $locations = $this->optionsList('location');
        $activities = $this->optionsList('activity');
        $moods = $this->optionsList('mood');
        $lightings = $this->optionsList('lighting');
        $cameras = $this->optionsList('camera');
        $props = $this->optionsList('prop');
        $carouselSchemaField = $postType === 'carousel' ? ',
  "carousel_slides": []' : '';
        $carouselRule = $postType === 'carousel'
            ? "\n- carousel_slides contiene da 3 a 5 frasi brevi (massimo 8-10 parole ciascuna), in italiano, pensate per essere lette sovrapposte a una foto come in un carosello \"quote card\": devono avere un filo narrativo comune legato al momento di oggi, essere leggibili a colpo d'occhio, senza hashtag o emoji dentro il testo della frase"
            : '';
        $newsSection = $this->buildNewsSection($character, $newsDigest);
        $instructionsSection = $this->buildInstructionsSection($character, $instructions);
        return <<<TXT
Sei il Character Engine di {$character->name}.
Di seguito trovi la documentazione del personaggio.
Usala per eseguire il task richiesto senza contraddire identità, voce, valori, memoria e regole.
=== DOCUMENTAZIONE ===
{$documentation}
=== MOMENTO DI OGGI ===
{$lifeContext}{$newsSection}{$instructionsSection}
Genera il post Instagram di oggi per {$character->name}.
Restituisci ESCLUSIVAMENTE un JSON valido.
Non usare markdown.
Non usare ```json.
Non aggiungere testo prima o dopo.
Schema:
{
  "rubrica": "",
  "titolo": "",
  "caption": "",
  "hashtags": [],
  "scene": {
    "character": "{$character->slug}",
    "location": "",
    "activity": "",
    "mood": "",
    "lighting": "",
    "camera": "instagram_vertical",
    "props": []
  },
  "comments": [],
  "replies": []{$carouselSchemaField}
}
Regole:
- caption in italiano
- hashtags massimo 6
- comments contiene esattamente 3 commenti
- replies contiene esattamente 3 risposte
- tono umano, ironico, semplice
- niente clickbait
- niente frasi da guru
- {$character->name} deve sembrare una persona reale, non un assistente AI
- il testo deve essere coerente con "time_of_day" e "season" indicati in MOMENTO DI OGGI: se time_of_day è mattina o pomeriggio, non riferirti a eventi già accaduti "stasera" o "stanotte"; se è sera o notte, puoi riferirti alla giornata appena trascorsa
- vestiti/attività citati nel testo devono essere coerenti con la stagione indicata{$carouselRule}
La scene deve usare SOLO questi valori.
location:
{$locations}
activity:
{$activities}
mood:
{$moods}
lighting:
{$lightings}
camera:
{$cameras}
props:
scegli massimo 5 tra:
{$props}
TXT;
    }
    public function buildCommentReplyPrompt(Character $character, string $commentText, ?string $commenterUsername = null): string
    {
        $documentation = $this->buildDocumentation($character, 'instagram_post');
        $who = $commenterUsername ? "@{$commenterUsername}" : 'una persona';
        return <<<TXT
Sei il Character Engine di {$character->name}.
Di seguito trovi la documentazione del personaggio.
Usala per rispondere senza contraddire identità, voce, valori e regole.
=== COMMENTO RICEVUTO ===
{$who} ha scritto sotto un post di {$character->name}: "{$commentText}"
Scrivi la risposta che {$character->name} darebbe a questo commento.
Regole:
- massimo 1-2 frasi brevi
- in italiano
- tono umano, caldo, informale, mai da assistente AI
- niente hashtag
- NON usare MAI emoji o simboli grafici (il font non riesce a disegnarli e appaiono come quadratini), altrimenti nessuna
- non ripetere parola per parola il commento
- se il commento è generico o poco chiaro rispondi comunque in modo cordiale e naturale
Restituisci SOLO il testo della risposta, senza virgolette, senza markdown, senza testo aggiuntivo.
TXT;
    }
    public function buildBatchCommentReplyPrompt(Character $character, array $comments): string
    {
        $documentation = $this->buildDocumentation($character, 'instagram_post');
        $lines = collect($comments)->map(function ($c) {
            $who = ! empty($c['username']) ? "@{$c['username']}" : 'una persona';
            return "- comment_id: \"{$c['id']}\" | {$who} ha scritto: \"{$c['text']}\"";
        })->implode("\n");
        return <<<TXT
Sei il Character Engine di {$character->name}.
Di seguito trovi la documentazione del personaggio.
Usala per rispondere senza contraddire identità, voce, valori e regole.
=== DOCUMENTAZIONE ===
{$documentation}
=== COMMENTI RICEVUTI ===
Qui sotto trovi una lista di commenti ricevuti sotto post di {$character->name}, ciascuno con un comment_id univoco.
{$lines}
Scrivi la risposta che {$character->name} darebbe a OGNUNO di questi commenti, uno per uno.
Regole per ogni risposta:
- massimo 1-2 frasi brevi
- in italiano
- tono umano, caldo, informale, mai da assistente AI
- niente hashtag
- NON usare MAI emoji o simboli grafici (il font non riesce a disegnarli e appaiono come quadratini), altrimenti nessuna
- non ripetere parola per parola il commento
- se il commento è generico o poco chiaro rispondi comunque in modo cordiale e naturale
- varia le risposte tra loro, non usare sempre la stessa formula anche se i commenti si assomigliano
Restituisci ESCLUSIVAMENTE un JSON valido, senza markdown, senza testo prima o dopo, con questo schema:
{
  "replies": [
    {"comment_id": "", "reply": ""}
  ]
}
Devi includere ESATTAMENTE un oggetto per ogni comment_id elencato sopra, copiando il comment_id in modo identico (stessa stringa, stesse cifre, tra virgolette).
TXT;
    }
    /**
     * Sceglie una notizia tra quelle trovate e scrive un commento brevissimo
     * per una Instagram Story (testo che verrà composto sopra un'immagine).
     */
    public function buildStoryCommentPrompt(Character $character, array $newsDigest, ?string $instructions = null): string
    {
        $documentation = $this->buildDocumentation($character, 'instagram_post');
        $lines = collect($newsDigest)->map(function ($n) {
            $desc = $n['description'] ? ": {$n['description']}" : '';
            return "- {$n['title']}{$desc}";
        })->implode("\n");
        $instructionsSection = $this->buildInstructionsSection($character, $instructions);
        return <<<TXT
Sei il Character Engine di {$character->name}.
Di seguito trovi la documentazione del personaggio.
Usala per scrivere senza contraddire identità, voce, valori e regole.
=== DOCUMENTAZIONE ===
{$documentation}
=== NOTIZIE RECENTI TRA CUI SCEGLIERE ===
{$lines}{$instructionsSection}
Scegli UNA sola di queste notizie, quella che più si presta a un commento personale e genuino di {$character->name}, e scrivi un breve testo per una Instagram STORY (non un post): una reazione a caldo, spontanea, diretta, massimo due righe brevi.
Regole:
- massimo 28-32 parole totali (4-5 righe corte)
- in italiano
- tono umano, diretto, come una nota veloce scritta di getto, mai da giornalista o da assistente AI
- niente hashtag
- niente virgolette attorno al testo
- NON usare MAI emoji o simboli grafici (il font non riesce a disegnarli e appaiono come quadratini)
- non deve sembrare un titolo di giornale né una rassegna stampa
Restituisci SOLO il testo della story, nient'altro.
TXT;
    }
    private function buildInstructionsSection(Character $character, ?string $instructions): string
    {
        if (! $instructions) {
            return '';
        }
        return <<<TXT

=== ISTRUZIONI SPECIFICHE PER OGGI ===
Chi gestisce {$character->name} ha lasciato queste indicazioni per il contenuto di oggi:
"{$instructions}"
Seguile quando possibile, ma restano secondarie rispetto all'identità, ai valori e alle regole di {$character->name}: se sono in conflitto con l'identità di {$character->name}, dai priorità all'identità del personaggio.
TXT;
    }
    private function buildNewsSection(Character $character, array $newsDigest): string
    {
        if (empty($newsDigest)) {
            return '';
        }
        $lines = collect($newsDigest)->map(function ($n) {
            $desc = $n['description'] ? ": {$n['description']}" : '';
            return "- {$n['title']}{$desc}";
        })->implode("\n");
        return <<<TXT
=== NOTIZIE RECENTI SUL TEMA (ultime 1-2 giornate) ===
{$lines}
Puoi prendere spunto da UNA di queste notizie per rendere il post di oggi più specifico e originale, raccontandola con parole tue e restando nel personaggio di {$character->name} — non fare la rassegna stampa, non citare la fonte giornalistica, non sembrare un bollettino. È solo uno spunto per uscire dal generico: se nessuna si adatta bene al tono di oggi, ignorale tutte e scrivi comunque un post naturale.
TXT;
    }
    private function buildDocumentation(Character $character, string $purpose): string
    {
        $sectionKeys = config("purposes.{$purpose}", config('purposes.instagram_post'));
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
        return $documentation;
    }
    private function buildLifeMoment(Character $character, LifeEvent $lifeEvent): array
    {
        $recent = TimelineEntry::where('character_id', $character->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
        return [
            'date' => now()->toDateString(),
            'time' => now()->format('H:i'),
            'time_of_day' => TemporalContext::timeOfDay(),
            'season' => TemporalContext::season(),
            'type' => 'daily_moment',
            'topic' => $lifeEvent->scene_hint['topic'] ?? $lifeEvent->title,
            'scene' => $lifeEvent->scene_hint,
            'context' => $this->buildContext($recent),
        ];
    }
    private function buildContext($recent): string
    {
        if ($recent->isEmpty()) {
            return "Non ci sono ancora eventi recenti registrati.";
        }
        $lines = $recent->map(function ($entry) {
            $scene = $entry->scene ?? [];
            $location = $scene['location'] ?? 'location non definita';
            $activity = $scene['activity'] ?? 'attività non definita';
            return "- {$entry->title} | topic: {$entry->topic} | location: {$location} | activity: {$activity}";
        });
        return "Contesto recente:\n\n" . $lines->implode("\n");
    }
    private function optionsList(string $category): string
    {
        $keys = SceneElement::whereNull('tenant_id')
            ->where('category', $category)
            ->orderBy('key')
            ->pluck('key');
        return $keys->map(fn ($key) => "- {$key}")->implode("\n");
    }
}
