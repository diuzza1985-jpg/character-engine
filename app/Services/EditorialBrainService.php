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
    private const REQUIRED_FIELDS = ['decisione', 'motivazione', 'formato', 'idea', 'testo', 'prompt_immagine', 'carousel_slides', 'storyline_da_aggiornare', 'used_news'];

    private function buildResponseSchema(bool $forcePublish, bool $diarioDisponibile = true): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => self::REQUIRED_FIELDS,
            'properties' => [
                'decisione' => [
                    'type' => 'string',
                    'enum' => $forcePublish ? ['pubblica'] : ['pubblica', 'non_pubblicare'],
                ],
                'motivazione' => ['type' => 'string'],
                'formato' => [
                    'type' => ['string', 'null'],
                    'enum' => $this->formatoEnum($diarioDisponibile),
                ],
                'idea' => ['type' => 'string'],
                'testo' => ['type' => ['string', 'null']],
                'prompt_immagine' => ['type' => ['string', 'null']],
                'carousel_slides' => ['type' => ['array', 'null'], 'items' => ['type' => 'string']],
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
    }

    /**
     * 'oggetto' è sempre disponibile: zero nuova infrastruttura, solo un prompt diverso (nessun
     * personaggio in scena). 'diario' invece è centellinato — non più di una volta ogni N post
     * (character_editorial_settings.diario_ogni_n_post, calcolato da EditorialCycleService, sezione
     * 3) — stesso principio già in uso per il pavimento di pubblicazione (11.12): è lo schema a
     * impedire l'abuso, non un'istruzione testuale che il modello potrebbe non rispettare sempre.
     */
    private function formatoEnum(bool $diarioDisponibile): array
    {
        // "reel" volutamente escluso (sez. 12.5, stesso principio di 11.13 per carousel):
        // non esiste alcuna pipeline di generazione video dietro questa opzione, solo
        // FalImageService (text-to-image). Il cervello editoriale lo decideva ma il sistema
        // pubblicava comunque una singola immagine statica come post normale, senza errori
        // né log — un gap silenzioso end-to-end. Meglio che il cervello non proponga mai un
        // formato che sappiamo rotto, finché una vera pipeline video non esiste.
        $enum = ['post', 'carousel', 'story', 'oggetto', null];
        if ($diarioDisponibile) {
            $enum[] = 'diario';
        }
        return $enum;
    }
    /**
     * @param bool $forcePublish true quando i giorni di silenzio del personaggio hanno superato
     *   la soglia configurata (character_editorial_settings.max_giorni_silenzio) — in quel caso
     *   "non_pubblicare" smette di essere un'opzione valida per questo ciclo.
     */
    public function decide(
        Character $character,
        array $context,
        bool $forcePublish = false,
        ?int $daysSinceLastPost = null,
        ?int $maxGiorniSilenzio = null,
        ?string $instructions = null,
        bool $diarioDisponibile = true,
    ): array {
        $prompt = $this->buildPrompt($character, $context, $forcePublish, $daysSinceLastPost, $maxGiorniSilenzio, $instructions, $diarioDisponibile);

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
                        'schema' => $this->buildResponseSchema($forcePublish, $diarioDisponibile),
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

        $this->validate($data, $forcePublish);

        return $data;
    }

    public function lastPrompt(
        Character $character,
        array $context,
        bool $forcePublish = false,
        ?int $daysSinceLastPost = null,
        ?int $maxGiorniSilenzio = null,
        ?string $instructions = null,
        bool $diarioDisponibile = true,
    ): string {
        return $this->buildPrompt($character, $context, $forcePublish, $daysSinceLastPost, $maxGiorniSilenzio, $instructions, $diarioDisponibile);
    }

    private function validate(array $data, bool $forcePublish = false): void
    {
        foreach (self::REQUIRED_FIELDS as $key) {
            if (! array_key_exists($key, $data)) {
                throw new RuntimeException("Campo mancante nella risposta del cervello editoriale: {$key}");
            }
        }
        $allowedDecisioni = $forcePublish ? ['pubblica'] : ['pubblica', 'non_pubblicare'];
        if (! in_array($data['decisione'], $allowedDecisioni, true)) {
            throw new RuntimeException("Valore non valido per decisione: {$data['decisione']}");
        }
        // Stesso principio di 11.13: meglio fallire qui che pubblicare un carousel incompleto
        // (PublishInstagramPostJob richiede comunque almeno 2 slide per il container Instagram).
        if ($data['decisione'] === 'pubblica' && $data['formato'] === 'carousel' && count($data['carousel_slides'] ?? []) < 2) {
            throw new RuntimeException('Formato carousel richiede almeno 2 carousel_slides, ricevute: ' . count($data['carousel_slides'] ?? []));
        }
    }

   private function buildPrompt(
        Character $character,
        array $context,
        bool $forcePublish = false,
        ?int $daysSinceLastPost = null,
        ?int $maxGiorniSilenzio = null,
        ?string $instructions = null,
        bool $diarioDisponibile = true,
    ): string {
        $oggi = $context['oggi'];
        $documentation = $context['bible'] !== '' ? $context['bible'] : 'Nessuna sezione di bible trovata.';
        $visualProfile = $this->formatVisualProfile($context['profilo_visivo']);
        $statoInterno = json_encode($context['stato_interno'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $storylines = $this->formatStorylines($context['storyline_rilevanti']);
        $relazioni = $this->formatRelazioni($context['relazioni_rilevanti']);
        $contenutiRecenti = $this->formatContenutiRecenti($context['contenuti_recenti']);
        $vitaRecente = $this->formatVitaRecente($context['vita_recente']);
        $istruzioniSpecifiche = ($instructions !== null && trim($instructions) !== '')
            ? "\n" . $this->buildInstructionsParagraph($instructions) . "\n"
            : '';
        $regolaSilenzio = $forcePublish
            ? "\n" . $this->buildForcedParagraph($character, $daysSinceLastPost, $maxGiorniSilenzio) . "\n"
            : '';
        $compitoIntro = $forcePublish
            ? "Decidi cosa pubblica oggi {$character->name}: la regola sopra rende \"pubblica\" l'unica decisione possibile per questo ciclo, quindi il tuo compito è trovare l'idea più onesta possibile, non decidere se pubblicare."
            : "Decidi se oggi {$character->name} pubblica qualcosa o no.\nNon è obbligatorio pubblicare ogni giorno: una persona vera non lo fa. Se lo stato interno, le storyline e i contenuti recenti non offrono niente di genuino da raccontare oggi, \"non_pubblicare\" è una scelta corretta, non un fallimento — in quel caso lascia formato/testo/prompt_immagine a null e spiega comunque in motivazione perché.";
        $istruzioniPubblicazione = $forcePublish ? 'Per l\'idea di oggi:' : 'Se decidi di pubblicare:';
        $formatiDisponibili = $this->describeFormatOptions($diarioDisponibile);

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
{$istruzioniSpecifiche}{$regolaSilenzio}
=== IL TUO COMPITO ===
{$compitoIntro}

    {$istruzioniPubblicazione}
    - scegli il formato più adatto all'idea, non il più comodo. Opzioni disponibili oggi: {$formatiDisponibili}
    - il testo/caption deve sembrare scritto da una persona reale, mai da un assistente AI, coerente con documentazione e stato interno
    - puoi proporre l'aggiornamento di UNA storyline con storyline_da_aggiornare, ma solo se il contenuto di oggi la fa avanzare davvero (non per il solo fatto di nominarla): storyline_id deve essere uno di quelli elencati sopra, mai un ID inventato; nuovo_stato è uno tra dormiente/aperta/in_pausa/risolta/abbandonata ("risolta" = chiusura con un finale soddisfacente, "abbandonata" = lasciata cadere senza un vero finale, "in_pausa" = si ferma ma resta aperta); se non stai aggiornando nessuna storyline, storyline_da_aggiornare deve essere null
    - prompt_immagine descrive la scena per un futuro generatore di immagini (tu non generi l'immagine): in inglese, concreto (soggetto, ambientazione, luce, inquadratura), coerente col profilo visivo sopra, senza testo da sovrapporre nell'immagine — anche per un carousel è UNA sola foto: le slide condividono la stessa immagine, con testo diverso sovrapposto sopra
    - prompt_immagine deve essere coerente con la stagione indicata sopra in OGGI (oggi è {$oggi['stagione']}): non vestire {$character->name} con capi non adatti alla stagione anche se il profilo visivo li descrive in generale — quel profilo descrive lo stile abituale del personaggio, non un obbligo da rispettare in ogni condizione climatica
    - prompt_immagine deve raffigurare SOLO {$character->name} come persona (o, per il formato "oggetto", nessuna persona). Anche se testo/idea nominano altre persone o animali (es. una persona con cui interagisce, o un animale domestico), NON descriverli visivamente in prompt_immagine: la loro presenza resta solo narrativa, nel testo — oggi non esiste un modo per garantire che il loro aspetto resti coerente da un post all'altro, quindi non li disegniamo affatto finché quel sistema non esiste
    - se scegli il formato "carousel": carousel_slides contiene da 3 a 5 frasi brevi (massimo 8-10 parole ciascuna), in italiano, pensate per essere lette sovrapposte alla foto come in un carosello "quote card" — devono avere un filo narrativo comune legato all'idea di oggi, essere leggibili a colpo d'occhio, senza hashtag o emoji dentro il testo della frase; testo resta la caption normale sotto il post (come per qualunque altro formato), distinta dalle frasi sovrapposte — non ripetere lì il contenuto delle slide. Per qualunque formato diverso da "carousel", carousel_slides deve essere null
    - se scegli il formato "oggetto": prompt_immagine descrive SOLO un oggetto o un dettaglio della scena, esplicitamente SENZA persone nell'inquadratura — deve essere qualcosa di specificamente legato a {$character->name}, dedotto dalla bible e dal profilo visivo sopra (non un oggetto generico che andrebbe bene per qualsiasi personaggio: guarda cosa emerge davvero dalla sua documentazione, dalla sua vita recente, dal suo mondo — può essere qualunque cosa, dipende solo da chi è lui/lei); testo (la caption) resta breve, quasi assente, lascia parlare l'immagine invece di spiegarla
    - se scegli il formato "diario": testo è un pensiero breve in prima persona, tono riflessivo e privato, frasi semplici, NESSUN hashtag e NESSUNA call-to-action — non è un contenuto promozionale, è più vicino a una pagina scritta per sé che qualcuno ha visto per caso; prompt_immagine descrive uno sfondo quieto coerente con lo stato interno di oggi, non serve un primo piano del personaggio
    - used_news è sempre false per ora: le notizie non sono ancora collegate a questo flusso

Restituisci ESCLUSIVAMENTE il JSON conforme allo schema fornito. Non usare markdown, non usare \`\`\`json, non aggiungere testo prima o dopo.
TXT;
    }

    /**
     * Elenco leggibile delle opzioni di formato per il prompt — riflette esattamente lo stesso
     * enum passato allo schema strutturato (formatoEnum), così testo e vincolo tecnico non
     * possono mai disallinearsi.
     */
    private function describeFormatOptions(bool $diarioDisponibile): string
    {
        $options = [
            'post (foto singola, il default)',
            'carousel (più slide con lo stesso filo narrativo)',
            'story',
            'oggetto (un dettaglio della sua vita, specifico per questo personaggio e dedotto dalla sua documentazione — non il personaggio in scena, ma qualcosa che lo racconta indirettamente)',
        ];

        if ($diarioDisponibile) {
            $options[] = 'diario (un pensiero breve e privato, quasi ad alta voce — usalo con parsimonia, è un registro raro, non il tono di tutti i giorni)';
        }

        return implode(', ', $options);
    }

    private function buildForcedParagraph(Character $character, ?int $daysSinceLastPost, ?int $maxGiorniSilenzio): string
    {
        $tempoTrascorso = $daysSinceLastPost === null
            ? "{$character->name} non ha ancora pubblicato nulla"
            : "sono passati {$daysSinceLastPost} giorni dall'ultimo contenuto di {$character->name}";

        return <<<TXT
=== REGOLA DI OGGI: LA PUBBLICAZIONE NON È OPZIONALE ===
{$tempoTrascorso}, oltre la soglia di silenzio consentita per questo personaggio ({$maxGiorniSilenzio} giorni). Oggi {$character->name} DEVE pubblicare qualcosa: "non_pubblicare" non è più una scelta disponibile per questo ciclo.
Questo non significa forzare un contenuto a caso: scegli comunque l'idea più onesta e meno forzata che riesci a trovare tra tutto quello che sai di {$character->name} — anche una story minimale o un pensiero breve vanno benissimo. L'obiettivo è restare presente, non riempire il vuoto con qualcosa di finto.
TXT;
    }

    /**
     * Istruzioni dal pannello di programmazione (ScheduledPost.instructions): indirizzano il
     * CONTENUTO, non forzano la pubblicazione — quel meccanismo resta solo il pavimento di
     * silenzio (buildForcedParagraph). Stesso principio già usato in
     * PromptBuilder::buildInstructionsSection() per la pipeline legacy: guida secondaria,
     * subordinata all'identità del personaggio.
     */
    private function buildInstructionsParagraph(string $instructions): string
    {
        return <<<TXT
=== ISTRUZIONI SPECIFICHE PER OGGI (dal pannello di programmazione) ===
"{$instructions}"
Tienile in forte considerazione nella scelta del contenuto e della storyline, ma resta comunque libero di valutare se pubblicare o no in base allo stato del personaggio — a meno che il pavimento di pubblicazione (giorni di silenzio) sopra non renda comunque obbligatoria la pubblicazione oggi.
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
