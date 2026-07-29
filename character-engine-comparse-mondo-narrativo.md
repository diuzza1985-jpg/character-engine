# Character Engine — Il mondo intorno al personaggio (comparse, mondo narrativo)

> Nota: questo file non esisteva nel repository — ricreato qui su richiesta esplicita, come
> destinazione locale per il lavoro descritto sotto. Se esiste una versione precedente in una
> cartella cloud collegata con decisioni aggiuntive, va riconciliata a mano con questa.

## Contesto

Caso reale che ha fatto nascere l'iniziativa: Sofia ha un marito (Fernando), un cane, un pesce
rosso e due figlie (Sara, Marta) — oggi solo parzialmente presenti nei contenuti pubblicati.
L'obiettivo è dare a un personaggio un mondo narrativo vero: persone/animali collegati con una
propria identità (non solo testo), generazione immagine con più soggetti, asset caricati
dall'utente, riduzione della ripetitività nei contenuti.

## Principio guida (vincolante, non derogabile)

**La bible di un personaggio è dato, non codice.** Ogni personaggio ha la propria bible
indipendente, non condivisa con altri — è del tutto legittimo scrivere/arricchire i testi della
bible di un personaggio specifico (agganci narrativi per una relazione, hobby, storyline) con
riferimenti diretti a lui/lei per nome.

**Il codice del cervello editoriale deve restare completamente generico.** Nessuna riga di
logica — `EditorialBrainService`, `EditorialContextBuilder`, `GenerateInstagramPostJob`,
qualunque servizio condiviso — può contenere un riferimento hardcoded a un personaggio o a una
relazione specifica (es. mai `"Sofia"` o `"Fernando"` come stringa in codice). Deve valere
identico per qualunque personaggio crei un domani un utente dal wizard. Stesso principio già
seguito e già corretto in passato (sez. 11.16 del project log, esempi "la SUA tazza, la SUA
scrivania" sostituiti con placeholder generici).

Verifica pratica prima di ogni commit su codice condiviso: `grep` del nome di qualunque
personaggio esistente sul diff — zero risultati attesi.

## Fase 0 — Investigazione (completata)

Prima di toccare qualunque cosa, indagine sulla bible reale di Sofia e sul prompt effettivamente
renderizzato, per capire la causa esatta di due problemi osservati in produzione.

### 1) Perché Fernando è sempre "quello che costruisce"

Non un singolo hardcoded, ma sovra-rappresentazione strutturale nella sua bible (query dirette su
`character_bible_sections`, character_id=3, verificate a mano):

- `famiglia`: intro generica su Fernando (lavoratore, onesto, paziente) + una passione alternativa
  mai sfruttata altrove ("Le sue passioni" → griglia/cucina) + un'intera sottosezione dedicata
  **"## Il costruttore della famiglia"** con l'aneddoto delle assi di legno, ripreso ancora in
  "## Il loro rapporto".
- `umorismo`: sezione nominata **`# Il tormentone di Fernando`** (running gag strutturato con
  varianti pronte), voce esplicita in "bersagli preferiti" → *"Fernando e i suoi progetti
  'semplicissimi'"*, aneddoto del cacciavite/veranda ripreso ancora in "Di cosa ride davvero
  Sofia".
- Prompt renderizzato (`EditorialBrainService::lastPrompt()`), sezione "Stories": tra 7 idee
  suggerite, l'unica su Fernando è *"Fernando che costruisce qualcosa di improbabile"*.

Fernando ha un solo aggancio narrativo "pronto all'uso", ripetuto 4-5 volte in punti diversi della
bible — gli altri tratti esistono ma non hanno mai un gancio comico/narrativo scritto da nessuna
parte.

**Fix (dato, non codice)**: aggiunto un secondo aggancio comico in `umorismo` → bersagli preferiti
("le grigliate epiche di Fernando"), riusando un tratto già stabilito in `famiglia` ma mai
sfruttato.

### 2) Perché Sofia parla quasi solo di bug/programmazione

Confermato empiricamente sugli 8 post reali pubblicati (character_id=3, esclusi i 2 `story_share`
senza caption propria): **tutti e 8** a tema tech/lavoro o Fernando-DIY. Zero post con Sara,
Marta, Pippo, Minnie o Sushi come soggetto principale, pur essendo tutti citati nella bible.

Cause trovate:

- Solo **due** storyline esistevano per Sofia in tutto il DB — *"Progetto con Fernando"* e
  *"Giornata con un cliente"* — nessuna su famiglia/animali/salute/hobby. Il prompt chiede di
  considerare le storyline con più pressione come candidate: con solo queste due, il richiamo
  verso tech/Fernando era strutturale.
- **Bug di codice reale, generico**: `TimelineEntry.topic` non veniva più popolato dal flusso
  attivo (`GenerateInstagramPostJob::handleEditorialBrainFlow()`) — solo il flusso legacy lo
  scriveva da `LifeEvent.scene_hint['topic']`. La sezione "vita recente" del prompt mostrava
  quindi sempre `topic: n/d`: nessun tracciamento strutturato di categoria, solo l'auto-giudizio
  del modello sul testo libero delle caption recenti (impedisce di ripetere l'evento esatto, non
  la categoria).
- Nessuna regola di rotazione tematica esiste nella bible stessa (sezione `regole` letta per
  intero: tutta su tono/etica, mai su varietà di argomento).

**Fix (codice, generico — verificato zero riferimenti a personaggi specifici nel diff)**:
- `EditorialBrainService`: nuovo campo `topic` nello schema strutturato (categoria libera e
  breve, es. "lavoro"/"famiglia"/"animali" — non un elenco fisso, il modello la sceglie da solo
  ogni volta).
- `GenerateInstagramPostJob::handleEditorialBrainFlow()`: passa `decision.topic` a
  `TimelineEntry::create()` (prima assente).
- `EditorialContextBuilder::buildTopicFrequency()`: conteggio deterministico (non
  un'impressione) di quante delle ultime 8 pubblicazioni condividono ogni topic, calcolato sulla
  cronologia reale di quel personaggio — generico per costruzione, nessuna categoria hardcoded.
- Nuova sezione "VARIETÀ DI ARGOMENTI" nel prompt con il conteggio reale e un'istruzione esplicita
  a scegliere altro se una categoria supera metà delle pubblicazioni recenti.

**Fix (dato, non codice)**: seminate due nuove storyline aperte per Sofia — *"Le domande
impossibili di Marta"*, *"Pippo e la gallina di gomma"* — per competere con la coppia
tech/Fernando-DIY nella pressione narrativa.

### 3) Le pipeline schedulate già filtrano per stato attivo

Verificato leggendo il codice reale:

- `CharacterLiveTick::resolveCharacters()`: percorso automatico già `where('status','active')`;
  percorso manuale (slug/ID esplicito) già blocca se `status !== 'active'`.
- `MaterializeWeeklySchedule`: già `WeeklyScheduleSlot::where('enabled',true)
  ->whereHas('character', fn($q)=>$q->where('status','active'))`.
- `ProcessScheduledPostJob`/`schedule:process-due`: operano solo su righe `scheduled_posts`
  esistenti, che per costruzione non possono esistere per un personaggio mai attivato.

**Conclusione**: un personaggio "comparsa" creato in `status: draft` è già completamente
dormiente su tutta la pipeline automatica — nessuna nuova colonna di stato/filtro necessaria.
Verificato anche con un caso reale in Fase 1 (vedi sotto).

## Fase 1 — Collegare le relazioni a personaggi veri (completata)

- Nuova colonna `character_relationships.related_character_id` (nullable, FK verso `characters`,
  `nullOnDelete` — se il personaggio collegato viene eliminato, la relazione testuale
  name/type/sentiment resta, torna solo narrativa).
- `CharacterRelationship::relatedCharacter()` — belongsTo.
- **Nessuna nuova colonna di "ruolo"** (niente `characters.role` o simili): un personaggio
  "secondario" è un `Character` normale, creato in `status: draft` e senza `social_account`
  collegato. Attivarlo un domani come profilo pubblicante a sé stante è la stessa identica
  operazione già possibile oggi per qualunque personaggio.
- **Verificato con un caso reale**, non solo in astratto: creato Fernando come `Character` vero
  (status `draft`, nessun social account) e collegato alla relazione testuale già esistente di
  Sofia (`character_relationships.id=2`). Confermato che è già completamente dormiente:
  `Character::where('status','active')` non lo include; `php artisan character:live-tick 9`
  (il suo id) rifiuta esplicitamente ("non è attivo, tick saltato").

## Fase 2 — Wizard: persone/animali del mondo del personaggio (in corso)

Piano (da verificare/adattare durante l'implementazione):

- `character_drafts` esteso con un elenco di persone/animali.
- `ConvertCharacterDraftToCharacter` crea per ciascuno un `Character` in draft (stesso meccanismo
  del personaggio principale, senza il passo di attivazione/registrazione) + la riga
  `character_relationships` collegata.
- Modulo biforcato per tipo fin dalla prima versione: ramo persona (nome, tipo di relazione,
  descrizione libera, foto opzionale) e ramo animale (nome, specie, razza opzionale, aspetto
  fisico breve, foto opzionale) — campi diversi, non una variante minore.
- Prima di decidere come gestire `character_visual_profile` per un animale (hair_description,
  wardrobe_notes ecc. — pensati per persone): verificare come li legge davvero il codice
  (`PromptBuilder`/`EditorialBrainService`) prima di scegliere riuso improprio, generalizzazione
  dei nomi, o profilo dedicato più snello.
- Chiude anche il bug noto di Pippo (nessuna foto di riferimento salvata oggi, causa di
  un'incoerenza visiva già osservata) — con l'asset reale si risolve alla radice.

## Fase 3 — Generazione immagine con più soggetti (da fare)

- Nuovo metodo in `FalImageService` (es. `generateWithMultipleSubjects()`) con
  `fal-ai/nano-banana-pro` (fino a 14 reference combinabili, coerenza fino a 5 persone, $0.15/
  immagine — da confermare con una chiamata reale) invece di `ideogram/character` (limite noto:
  usa solo la prima reference).
- Si attiva quando la decisione del cervello editoriale nomina una relazione con
  `related_character_id` valorizzato e almeno un asset associato.
- Verifica richiesta: funzionamento tecnico reale (compone davvero entrambi i soggetti) E
  controllo visivo di coerenza di stile con quanto già stabilito per il personaggio principale.

## Fase 4 — Asset caricati dall'utente (da fare, indipendente)

- Nuovo campo `character_assets.source` (`ai_generated`/`user_uploaded`) — quando
  `user_uploaded`, salta del tutto `FalImageService` e qualunque addebito crediti.
- Flusso di consenso: checkbox esplicita e separata dai Termini di Servizio generali, testo
  chiaro sui diritti dell'immagine, evento di consenso auditable (timestamp, utente, versione del
  testo accettato) — non solo una spunta UI senza traccia lato server.
- Il testo legale esatto va rivisto da un legale prima del lancio pubblico — qui si costruisce
  solo il meccanismo tecnico e il punto dove quel testo andrà inserito.
