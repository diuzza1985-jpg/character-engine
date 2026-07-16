<?php
namespace App\Services;
use App\Models\Character;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Una sola chiamata GPT (11.7): legge il contesto già assemblato deterministicamente da
 * EditorialContextBuilder e decide se e cosa pubblicare oggi. Niente SDK di terze parti,
 * stesso principio già adottato per Instagram (sezione 9) e per OpenAiTextService: Http::
 * diretto contro l'API OpenAI. Usa Structured Outputs (json_schema, strict) per garantire
 * la forma del JSON di ritorno.
 */
class EditorialBrainService
{
    private const RESPONSE_SCHEMA = [
        'type' => 'object',
        'additionalProperties' => false,
        'required' => ['decisione', 'motivazione', 'formato', 'idea', 'testo', 'prompt_immagine', 'storyline_da_aggiornare', 'used_news'],
        'properties' => [
            'decisione' => ['type' => 'string', 'enum' => ['pubblica', 'non_pubblicare']],
            'motivazione' => ['type' => 'string'],
            'formato' => ['type' => ['string', 'null'], 'enum' => ['post', 'carousel', 'reel', 'story', null]],
            'idea' => ['type' => 'string'],
            'testo' => ['type' => ['string', 'null']],
            'prompt_immagine' => ['type' => ['string', 'null']],
            'storyline_da_aggiornare' => [
                'type' => ['object', 'null'],
                'additionalProperties' => false,
                'required' => ['storyline_id', 'nuovo_stato', 'note'],
                'properties' => [
                    'storyline_id' => ['type' => 'integer'],
                    'nuovo_stato' => ['type' => 'string', 'enum' => ['dormiente', 'aperta', 'in_pausa', 'risolta', 'abbandonata']],
                    'note' => ['type' => 'string'],
                ],
            ],
            'used_news' => ['type' => 'boolean'],
        ],
    ];

    public function decide(Character $character, array $context): array
    {
        $prompt = $this->buildPrompt($character, $context);

        $response = Http::withToken(config('services.openai.key'))
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('services.openai.text_model'),
                'messages' => [
                    ['role' => 'user', 'content' => $prompt],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'decisione_editoriale',
                        'strict' => true,
                        'schema' => self::RESPONSE_SCHEMA,
                    ],
                ],
            ])
            ->throw()
            ->json();

        $content = $response['choices'][0]['message']['content'] ?? null;
        if (! $content) {
            throw new RuntimeException('Il cervello editoriale non ha restituito contenuto.');
        }

        $data = json_decode($content, true);
        if (! is_array($data)) {
            throw new RuntimeException('Il cervello editoriale non ha restituito un JSON valido: ' . $content);
        }

        $this->validate($data);

        return $data;
    }

    public function lastPrompt(Character $character, array $context): string
    {
        return $this->buildPrompt($character, $context);
    }

    private function validate(array $data): void
    {
        foreach (array_keys(self::RESPONSE_SCHEMA['properties']) as $key) {
            if (! array_key_exists($key, $data)) {
                throw new RuntimeException("Campo mancante nella risposta del cervello editoriale: {$key}");
            }
        }
        if (! in_array($data['decisione'], ['pubblica', 'non_pubblicare'], true)) {
            throw new RuntimeException("Valore non valido per decisione: {$data['decisione']}");
        }
    }

    private function buildPrompt(Character $character, array $context): string
    {
        $oggi = $context['oggi'];
        $documentation = $context['bible'] !== '' ? $context['bible'] : 'Nessuna sezione di bible trovata.';
        $visualProfile = $this->formatVisualProfile($context['profilo_visivo']);
        $statoInterno = json_encode($context['stato_interno'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $storylines = $this->formatStorylines($context['storyline_rilevanti']);
        $relazioni = $this->formatRelazioni($context['relazioni_rilevanti']);
        $contenutiRecenti = $this->formatContenutiRecenti($context['contenuti_recenti']);
        $vitaRecente = $this->formatVitaRecente($context['vita_recente']);

        return <<<TXT
Sei il cervello editoriale di {$character->name}.
Il tuo compito NON è scrivere un contenuto "a comando": è decidere, come farebbe {$character->name} in prima persona, se oggi ha senso pubblicare qualcosa su Instagram e cosa.

=== CHI È {$character->name} ===
{$documentation}

=== PROFILO VISIVO (per coerenza, anche se l'immagine non viene generata da questa chiamata) ===
{$visualProfile}

=== OGGI ===
{$oggi['data']}, {$oggi['ora']} ({$oggi['momento_del_giorno']}, {$oggi['stagione']})

=== STATO INTERNO DI OGGI (privato — il pubblico non lo vede mai) ===
{$statoInterno}

Questi numeri non escono mai all'esterno così come sono. Possono trasparire solo come sfumatura nel tono o nella scelta se pubblicare (es. energia bassa → un contenuto più tranquillo, o nessun contenuto, non una frase tipo "oggi ho poca energia"). Non citare MAI valori numerici, nomi di variabili o termini come "mood", "drive", "storyline", "pressione", "sentiment" dentro testo/idea/prompt_immagine.

=== STORYLINE APERTE CON PIÙ PRESSIONE (candidate a essere riprese oggi) ===
{$storylines}

=== RELAZIONI RECENTI ===
{$relazioni}

=== CONTENUTI GIÀ PUBBLICATI DI RECENTE (non ripetere formato/argomento) ===
{$contenutiRecenti}

=== VITA RECENTE (eventi già vissuti, pubblicati o no) ===
{$vitaRecente}

=== IL TUO COMPITO ===
Decidi se oggi {$character->name} pubblica qualcosa o no.
Non è obbligatorio pubblicare ogni giorno: una persona vera non lo fa. Se lo stato interno, le storyline e i contenuti recenti non offrono niente di genuino da raccontare oggi, "non_pubblicare" è una scelta corretta, non un fallimento — in quel caso lascia formato/testo/prompt_immagine a null e spiega comunque in motivazione perché.

Se decidi di pubblicare:
- scegli il formato più adatto all'idea (post, carousel, reel o story), non il più comodo
- il testo/caption deve sembrare scritto da una persona reale, mai da un assistente AI, coerente con documentazione e stato interno
- puoi proporre l'aggiornamento di UNA storyline con storyline_da_aggiornare, ma solo se il contenuto di oggi la fa avanzare davvero (non per il solo fatto di nominarla): storyline_id deve essere uno di quelli elencati sopra, mai un ID inventato; nuovo_stato è uno tra dormiente/aperta/in_pausa/risolta/abbandonata ("risolta" = chiusura con un finale soddisfacente, "abbandonata" = lasciata cadere senza un vero finale, "in_pausa" = si ferma ma resta aperta); se non stai aggiornando nessuna storyline, storyline_da_aggiornare deve essere null
- prompt_immagine descrive la scena per un futuro generatore di immagini (tu non generi l'immagine): in inglese, concreto (soggetto, ambientazione, luce, inquadratura), coerente col profilo visivo sopra, senza testo da sovrapporre nell'immagine
- used_news è sempre false per ora: le notizie non sono ancora collegate a questo flusso

Restituisci ESCLUSIVAMENTE il JSON conforme allo schema fornito. Non usare markdown, non usare \`\`\`json, non aggiungere testo prima o dopo.
TXT;
    }

    private function formatVisualProfile(?array $profile): string
    {
        if (! $profile) {
            return 'Non definito.';
        }

        $lines = [];
        foreach ($profile as $label => $value) {
            $lines[] = "- {$label}: {$value}";
        }

        return implode("\n", $lines);
    }

    private function formatStorylines(array $storylines): string
    {
        if (empty($storylines)) {
            return 'Nessuna storyline aperta al momento.';
        }

        $lines = [];
        foreach ($storylines as $s) {
            $giorni = $s['giorni_da_ultimo_aggiornamento'] ?? '?';
            $lines[] = "- id {$s['id']} \"{$s['titolo']}\" (stato: {$s['stato']}, importanza {$s['importanza']}, pressione {$s['pressione']}, ultimo aggiornamento {$giorni} giorni fa)";
        }

        return implode("\n", $lines);
    }

    private function formatRelazioni(array $relazioni): string
    {
        if (empty($relazioni)) {
            return 'Nessuna relazione registrata.';
        }

        $lines = [];
        foreach ($relazioni as $r) {
            $tipo = $r['tipo'] ?? 'non specificato';
            $ultima = $r['ultima_interazione'] ?? 'mai';
            $lines[] = "- {$r['nome']} (tipo: {$tipo}, sentiment {$r['sentiment']}, ultima interazione: {$ultima})";
        }

        return implode("\n", $lines);
    }

    private function formatContenutiRecenti(array $contenuti): string
    {
        if (empty($contenuti)) {
            return 'Nessun contenuto pubblicato finora.';
        }

        $lines = [];
        foreach ($contenuti as $c) {
            $estratto = $c['estratto_caption'] ?? '(senza testo)';
            $lines[] = "- {$c['data']} | {$c['formato']} | {$c['stato']} | {$estratto}";
        }

        return implode("\n", $lines);
    }

    private function formatVitaRecente(array $eventi): string
    {
        if (empty($eventi)) {
            return 'Nessun evento recente registrato.';
        }

        $lines = [];
        foreach ($eventi as $e) {
            $topic = $e['topic'] ?? 'n/d';
            $lines[] = "- {$e['titolo']} (topic: {$topic}) — {$e['giorni_fa']} giorni fa";
        }

        return implode("\n", $lines);
    }
}
