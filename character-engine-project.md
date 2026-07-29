# Character Engine — Documento di progetto

*Ultimo aggiornamento: 29 luglio 2026. Carica questo file all'inizio di una nuova conversazione per riprendere il lavoro senza dover ripartire da zero.*

## 1. Visione

Character Engine è un sistema per gestire influencer virtuali AI (personaggi con bible testuale, profilo visivo coerente, libreria di scene) che generano e pubblicano contenuti su Instagram (post, reel, stories) mantenendo coerenza visiva nel tempo. Obiettivo finale: trasformarlo in un CMS multi-tenant SaaS, utilizzabile da chiunque voglia creare i propri personaggi virtuali, non solo per uso personale.

Il progetto nasce da un prototipo PHP funzionante (chiamato da n8n via chiamate HTTP a `generate.php` / `build_image_prompt.php` / `generate_image.php`) con un personaggio, Sofia, già completamente costruito (bible in 17 file, profilo visivo, ~100 elementi di scena, asset immagine). Decisione presa: **riscrittura completa in Laravel**, partendo da zero (nessun DB/installazione preesistente), migrando poi i dati di Sofia con comandi Artisan automatici invece di inserirli a mano.

## 2. Dove vive il progetto e come si avvia

- Ambiente: **WSL2 (Ubuntu)** su Windows — non sul filesystem Windows diretto (`D:\...`), per performance Docker.
- Percorso progetto Laravel: `~/character-engine-app` (= `/home/diuzz/character-engine-app` dentro Ubuntu).
- Percorso vecchio prototipo PHP (solo lettura, fonte per gli import): `/mnt/d/Volume/lavorocla/ai agent/character-engine`.
- Si apre con VS Code tramite estensione **WSL**: da terminale Ubuntu, dentro la cartella del progetto, `code .`.

```bash
./vendor/bin/sail up -d        # avvia tutti i container
./vendor/bin/sail down         # ferma tutto
./vendor/bin/sail restart      # riavvia (utile se qualcosa sembra "bloccato")
./vendor/bin/sail artisan ...  # qualsiasi comando artisan, gira dentro il container
```

App su **http://localhost** — pannello admin Filament su **http://localhost/aiadmin** (⚠️ non `/admin`, l'ID scelto in fase di installazione è "aiadmin"). Login con le credenziali create via `make:filament-user`.

**Porte personalizzate** (per evitare conflitti con mariadb/redis già attivi sul PC per altri progetti, es. n8n):

```
FORWARD_DB_PORT=3307      # invece di 3306
FORWARD_REDIS_PORT=6380   # invece di 6379
```

`DB_PORT`/`REDIS_PORT` restano a 3306/6379 (porte *interne* alla rete Docker, non toccarle).

⚠️ **Dipendenza non ovvia scoperta il 16-17 luglio**: per qualunque test di pubblicazione Instagram reale (non solo bozze), `APP_URL` punta a un tunnel **ngrok** che espone l'app locale su un dominio pubblico (`waking-dispense-uniformed.ngrok-free.dev`) — necessario perché Meta deve poter scaricare l'immagine da un URL pubblico raggiungibile, cosa che `http://localhost` non è. Il tunnel **non parte da solo con `sail up`**: va avviato a mano (`ngrok http 80`, verificare sempre la porta reale con `sail ps` invece di assumerla — l'authtoken è già in `~/.config/ngrok/ngrok.yml`, nessun altro setup trovato nel progetto). Se un test di pubblicazione fallisce con un errore Meta tipo "Only photo or video can be accepted as media type", la prima cosa da controllare è se il tunnel ngrok è vivo (`curl` sull'URL pubblico) prima di sospettare il codice. Buona notizia verificata il 17 luglio: il dominio pubblico è risultato **stabile tra un riavvio e l'altro** (probabile dominio statico gratuito dell'account ngrok) — non serve aggiornare `APP_URL` ad ogni riavvio, salvo conferma contraria dalla dashboard ngrok. Il tunnel espone l'app pubblicamente finché resta acceso: da fermare (`pkill ngrok`) quando non si stanno facendo verifiche di pubblicazione. Caso reale e primo successo in 11.13.

## 3. Stack installato

Laravel 13.18.1, PHP 8.5.8, **Laravel Cashier** (Stripe — collegato al modello `Tenant`, non `User`, scelta deliberata multi-tenant), **Filament v5.6.8**. `APP_LOCALE=it` per interfaccia in italiano.

## 4. Schema dati (25 migration) e Model

Multi-tenancy: **schema condiviso, `tenant_id` su ogni tabella rilevante** (non un DB per cliente).

**Account e fatturazione**
- `tenants` — cliente del CMS (fatturabile via Cashier): `id, name, email, stripe_customer_id, plan_id, status (trial/active/suspended)`
- `users` — `id, tenant_id, name, email, password, role (owner/editor/viewer)`
- `character_user` (pivot opzionale, per permessi granulari per-personaggio quando servirà)
- `plans` — `id, name, price_monthly, credits_included, max_characters, max_generations_per_day, stripe_price_id`
- `credit_packages` — ricariche acquistabili a parte: `id, name, credits, price, stripe_price_id`
- `credit_ledger` — libro mastro crediti, mai un saldo mutabile in place: `id, tenant_id, amount (+/-), balance_after, type (subscription_grant|topup|generation_debit|refund), reference_type, reference_id, description, created_at`. **Regola chiave**: il credito si scala PRIMA di chiamare OpenAI/fal.ai, non dopo; se la generazione fallisce si fa un movimento di rimborso.

**Personaggi e bible testuale**
- `characters` — `id, tenant_id, name, slug, status (draft/active/paused)`
- `character_bible_sections` — sostituisce i 17 file numerati del prototipo: `id, character_id, section_key, content (text), version, updated_at`
- `character_visual_profile` — 1:1, tratti fissi di coerenza visiva: `id, character_id, age_description, face_description, hair_description, wardrobe_notes, visual_rules_text`
- `character_assets` — foto di riferimento vere: `id, character_id, type, label, file_path (relativo al disco Storage), metadata json, is_default, created_at`. File fisici via **Storage facade di Laravel** (non path assoluti), organizzati `characters/{tenant_id}/{character_id}/...`, così il giorno del passaggio a S3/R2 è solo un cambio di config disco.

**Visual bible condivisa**
- `scene_elements` — libreria globale/condivisa: `id, tenant_id (NULL = globale), category (location/prop/mood/lighting/camera/activity), key, name, description, rules_text, camera_notes, lighting_notes, image_asset_id (nullable), created_at`
- `character_scene_elements` (pivot) — quali elementi (globali o custom) usa davvero questo personaggio: `character_id, scene_element_id`

**Motore di vita / memoria**
- `life_events` — pool eventi possibili: `id, character_id, category, title, weight, cooldown_days, scene_hint json` (+ da 16 luglio: `mood_deltas json`, `can_start_storyline bool`, `target_relationship string nullable`)
- `timeline_entries` — storico reale generato: `id, character_id, life_event_id (nullable), topic, scene json, title, created_at` (+ da 16 luglio: `storyline_id nullable FK`)
- `character_state` — 1:1 con `characters`, aggiunta 16 luglio: `mood_energia/valenza/stress (float)`, `baseline_energia/valenza/stress (float)`, `drives (json)`, `last_tick_at`
- `storylines` — aggiunta 16 luglio: `id, character_id, title, status (dormiente/aperta/in_pausa/risolta/abbandonata), importance (float), started_at, last_updated_at, resolution_notes`
- `character_relationships` — aggiunta 16 luglio: `id, character_id, name, type, sentiment (float), last_interaction_at, notes`
- `editorial_decisions` — aggiunta 16 luglio, non ancora popolata (in attesa del cervello editoriale, sezione 11.7): `id, generation_id, decisione, motivazione, storyline_id (nullable), used_news (bool)`

**Generazione e pubblicazione**
- `generations` — una riga per ogni esecuzione pipeline (audit trail margine crediti vs costo reale): `id, character_id, tenant_id, purpose (instagram_post/image_prompt/reply_comment/story), status (pending/processing/completed/failed), input json, output json, provider_costs json, credits_charged, credit_ledger_id, created_at, completed_at`
- `posts` — contenuto pronto/pubblicato: `id, character_id, generation_id, platform, platform_post_id, scheduled_at, published_at, status (draft/scheduled/published/failed), caption, media_urls json`
- `social_accounts` — credenziali Instagram collegate (dettaglio completo nella sezione 9): `id, character_id, platform, ig_user_id, access_token (cifrato), token_expires_at, status, last_refreshed_at, created_at`

**Model Eloquent** in `app/Models/`: i 15 originali (`User`, `Tenant` con trait `Billable`/helper `creditBalance()`, `Plan`, `Character`, `CharacterBibleSection`, `CharacterVisualProfile`, `CharacterAsset`, `SceneElement` con `scopeGlobal()`, `LifeEvent`, `TimelineEntry`, `Generation`, `Post`, `SocialAccount` con cast `access_token`→`encrypted`/`token_expires_at`→`datetime`, `CreditLedger`, `CreditPackage`) + da 16 luglio `CharacterState`, `Storyline` (con `pressure()`/`daysSinceLastUpdate()` calcolati al volo, non persistiti), `CharacterRelationship`, `EditorialDecision`.

## 5. Pannello admin Filament

**Risorse (CRUD completo, etichette in italiano — eccetto "Trial", lasciato in inglese di proposito):**
- **Tenants** ("Clienti") — nome, email, piano, stato (Trial/Attivo/Sospeso)
- **Characters** ("Personaggi") — cliente, nome, slug (autocompilato), stato (Bozza/Attivo/In pausa)
- **Plans** ("Piani") — nome, prezzo mensile (euro), crediti inclusi, limiti
- **SceneElements** ("Libreria scene") — libreria condivisa location/props/mood/lighting/camera/activity, `tenant_id` facoltativo (vuoto = condiviso con tutti)

**Relation Manager dentro la scheda di un personaggio:**
- **Bible Sections** — 16 sezioni testuali standard, dropdown + area di testo grande
- **Assets** ("Foto di riferimento") — `FileUpload` reale, cartella `characters/{tenant_id}/{character_id}/`, anteprima in tabella
- **SceneElements** ("Elementi di scena") — Attach/Detach dalla libreria condivisa (non crea nuovi elementi), `->preloadRecordSelect()` applicato per evitare dropdown vuoto/lento
- **VisualProfile** ("Profilo visivo") — relazione 1:1, pulsante "Crea" nascosto se il profilo esiste già

Non ancora fatto: nessuna vista admin per `character_state`/`storylines`/`character_relationships`/`editorial_decisions` (sezione 11) — per ora si ispezionano solo via tinker, da valutare se aggiungere Relation Manager quando il cervello editoriale sarà in funzione e ci sarà davvero qualcosa da rivedere a mano.

## 6. Concetti Filament imparati (riferimento rapido)

```
Tabella (database)
   ↓
Model (classe PHP 1:1 con la tabella + relazioni)
   ↓
Resource (interfaccia admin per gestire UN Model — CRUD completo)
   ↓
Relation Manager (scheda dentro una Resource, sfrutta una relazione del Model
                   per mostrare/gestire record di UN ALTRO Model)
```

- `make:filament-resource`/`make:filament-relation-manager` **leggono davvero lo schema del DB** per abbozzare i campi (rispondere sempre "Yes" a "generare dalla struttura del database?"), ma non conoscono il *significato* delle colonne: un `varchar` diventa sempre `TextInput` anche se dovrebbe essere upload immagine (successo con `file_path`) o una relationship-select (successo con `image_asset_id`, un FK bigint scambiato per "immagine" solo per il nome) — si corregge sempre a mano dopo.
- Il nome di relazione passato al generatore deve corrispondere **esattamente** al metodo già scritto nel Model.
- Per `hasOne` non c'è un tipo dedicato: si sceglie "Other" e si usa comunque un Relation Manager.
- Per `belongsToMany`, il generatore crea Attach/Detach (corretto: si collegano record esistenti). Il menu selezione va reso "precaricato" con `->preloadRecordSelect()` sull'`AttachAction`, altrimenti resta vuoto finché non si digita (nome diverso da `->preload()` usato sui Select normali — quirk dell'API Filament).
- Filament v5 è ristrutturato in classi separate: `Schemas/XForm.php` (form) e `Tables/XTable.php` (tabelle), non più metodi inline come in v3 — strategia adottata: generare sempre prima con l'artisan command e ispezionare l'output reale, invece di fidarsi di conoscenza potenzialmente non aggiornata.

## 7. Problemi incontrati e risolti (ambiente/Docker/Filament)

- **Un errore di sintassi PHP in un file Filament blocca la registrazione di TUTTE le risorse del pannello**, non solo quella toccata — prima cosa da controllare quando una sezione sparisce dal menu: `storage/logs/laravel.log` (`tail -n 60`), eventualmente `./vendor/bin/sail restart` per escludere cache/stato del container. Causa reale incontrata: doppia virgola in un array incollato ("Cannot use empty array elements in arrays").
- Il pannello è su `/aiadmin`, non `/admin` — attenzione con `route:list --path=admin`, "admin" è sottostringa di "aiadmin" e può ingannare (falso positivo).
- Conflitti porta MySQL/Redis con container preesistenti (n8n) → risolti con `FORWARD_DB_PORT`/`FORWARD_REDIS_PORT` (non toccare `DB_PORT`/`REDIS_PORT`, sono interni alla rete Docker).
- Permessi storage (`tempnam()` error) → `sail root-shell` poi `chown -R sail:sail storage bootstrap/cache && chmod -R 775 storage bootstrap/cache`.
- Container mysql/redis orfani dalla network dopo un `sail up` fallito → `docker rm -f <container>` poi `sail up -d` per ricrearli agganciati correttamente.
- `app/Console/Commands/` **non esiste di default** in Laravel 11+/13 (rimossa dallo skeleton) — va creata a mano (`mkdir -p app/Console/Commands`) prima di poter scrivere un Command via `cat > ... << 'PHP'`, altrimenti l'heredoc fallisce silenziosamente e solo il comando successivo nella pipeline dà l'illusione di successo.
- **Carbon: attenzione al segno nelle differenze di date** (incontrato nel motore di vita, sezione 11) — `diffInDays` tra due date può restituire un valore col segno sbagliato se l'ordine degli oggetti su cui si chiama il metodo è invertito rispetto a quanto ci si aspetta. Sintomo: un "giorni trascorsi da" che risulta negativo. Controllare sempre con un caso reale in tinker, non fidarsi della sola lettura del codice.
- **Docker Desktop non partito silenziosamente blocca Sail** senza errore ovvio finché non si prova un comando — controllare con `docker info` (se dà errore, Docker daemon è giù) prima di dare per scontato un problema nel codice.

## 8. Import dati legacy — completato (9 luglio 2026)

Migrazione automatica (no inserimento a mano) dei contenuti già creati per Sofia nel vecchio prototipo PHP, tramite due Artisan Command idempotenti (`updateOrCreate`/`firstOrCreate`, rilanciabili in sicurezza).

### 8.1 `import:legacy-character {slug} {--tenant=} {--name=}`

File: `app/Console/Commands/ImportLegacyCharacter.php`. Legge da `storage/app/legacy-import/{slug}/{bible,visual,assets}/` (file da copiare manualmente prima con `cp -r` da `/mnt/d/.../character-engine/characters/sofia/*.md` e `/mnt/d/.../visual_bible/characters/sofia/*.md` e `/mnt/d/.../visual_bible/assets/characters/sofia/*.png`).

- **Bible**: ogni `NN_nome.md` → riga in `character_bible_sections` (chiave = nome senza prefisso numerico), skip se contenuto vuoto.
- **Profilo visivo**: `appearance.md` + `description.md` → `face_description`; `wardrobe.md` → `wardrobe_notes`; `visual_rules.md` → `visual_rules_text`. Età/capelli restano vuoti (il vecchio sistema non li separava — da compilare a mano).
- **Asset**: ogni `.png` → riga in `character_assets`, file copiato su Storage in `characters/{tenant_id}/{character_id}/`, `is_default` letto da `asset.json`.

**Risultato ottenuto per Sofia** (id 2, tenant Claudia): 16 sezioni bible (17 file meno `14_memoria_sessione.md`, vuoto), profilo visivo importato, 6 asset immagine (face, full_body, reference_sheet, smile, thinking, write).

### 8.2 `import:legacy-scene-elements {--attach-character=} {--source=scene-library}`

File: `app/Console/Commands/ImportLegacySceneElements.php`. Legge da `storage/app/legacy-import/scene-library/{locations,props,mood,lighting,camera,activities}/` (copiato da `visual_bible/` del prototipo — quella è la cartella realmente letta da `SceneLoader.php`, non le copie/mirror trovate altrove come `characters/sofia/visual_bible_full/`, non più referenziate da codice attivo).

Mappatura scoperta ispezionando `SceneLoader.php`/`SceneSelector.php` del prototipo:
- **location** (cartella `locations/{key}/`: `description.md`, `camera.md`, `lighting.md`, `rules.md`, `objects.md`) → `name` (dall'intestazione `# Titolo` di `description.md`, fallback `Str::headline($key)`), `description`, `camera_notes`, `lighting_notes`, `rules_text` (= rules.md + objects.md)
- **prop** (cartella `props/{key}/`: `description.md`, `rules.md`) → `name`, `description`, `rules_text`
- **mood / lighting / camera / activity** (file singolo `{key}.md`) → `name` (da intestazione), `description`

Tutti creati con `tenant_id = NULL` (libreria globale condivisa), poi collegati al personaggio passato con `--attach-character=slug` tramite la pivot `character_scene_elements` (evita l'Attach manuale in Filament).

**Risultato ottenuto**: ~100 elementi importati (26 location, 37 props, 8 mood, 6 lighting, 9 camera, 14 activity) e collegati a Sofia. Verificato nel pannello: Libreria scene popolata, scheda Sofia → Elementi di scena già collegati.

### 8.3 Scoperta collaterale, non ancora sfruttata

Dentro `visual_bible/characters/` del vecchio prototipo esistono abbozzi di **altri personaggi oltre Sofia**: `fernando`, `marta`, `minnie`, `pippo`, `sara`, `sushi` (stessa struttura: `appearance.md`, `description.md`, `personality.md`, `visual_rules.md`, `wardrobe.md`, cartella `references/`). Inoltre `visual_bible/global/` contiene regole di stile/cultura generali non legate a un personaggio (`cultural_rules.md`, `image_rules.md`, `negative_prompt.md`, `style.md`), e ci sono immagini di riferimento per location/oggetti in `visual_bible/assets/locations/` e `visual_bible/assets/objects/` non ancora collegate a `scene_elements.image_asset_id`. Nessuno di questi è stato importato — da valutare se/quando servirà.

## 9. Pubblicazione Instagram — disegno tecnico (non ancora implementato in codice)

### Cosa è cambiato nell'API Meta (verificato, cambia spesso)

Da metà 2024 esiste la **"Instagram API with Instagram Login"** (Business Login per Instagram): un cliente collega il proprio account Instagram Business/Creator **senza dover avere una Pagina Facebook collegata** (prima era obbligatoria). Semplificazione enorme per l'onboarding di creator non tecnici.

⚠️ Punto bloccante non tecnico: per usare `instagram_business_content_publish` per conto di account non tuoi (i clienti — esattamente il caso d'uso), Meta richiede **App Review con Advanced Access**: serve documentare l'uso, un video del flusso end-to-end, e aver già fatto almeno una chiamata reale in development prima di richiederlo. Tempi di giorni/settimane — **da avviare presto, in parallelo allo sviluppo**, non a ridosso del lancio.

### Tabella `social_accounts` — cast Eloquent

```php
class SocialAccount extends Model
{
    protected $casts = [
        'access_token' => 'encrypted',
        'token_expires_at' => 'datetime',
    ];
}
```

### Flusso di connessione (OAuth)

1. Il cliente clicca "Collega Instagram" nella dashboard del suo personaggio.
2. Redirect al Business Login di Meta con scope: `instagram_business_basic`, `instagram_business_content_publish` (+ `instagram_business_manage_comments` se un domani si gestiranno anche le risposte ai commenti — coerente col `reply_comment` già previsto).
3. Callback: code → short-lived token → long-lived token (60 giorni).
4. Salvataggio `ig_user_id`, `access_token` (cifrato), `token_expires_at`, stato `connected`.

### Pipeline di pubblicazione

Job Laravel per personaggio/post (non sincrono in request HTTP — reel possono richiedere secondi/minuti, serve retry su rate limit):

```php
class PublishInstagramPostJob implements ShouldQueue
{
    public $tries = 5;
    public $backoff = [30, 60, 300, 900, 3600]; // backoff crescente sui rate limit

    public function __construct(private int $postId) {}

    public function handle(): void
    {
        $post = Post::with('character.socialAccount')->findOrFail($this->postId);
        $account = $post->character->socialAccount;

        if ($account->status !== 'connected') {
            $post->update(['status' => 'failed']);
            return; // notificare il tenant: serve ricollegare l'account
        }

        $mediaUrl = $this->publicUrlFor($post); // deve essere raggiungibile pubblicamente da Meta

        $container = Http::baseUrl('https://graph.instagram.com')
            ->post("/{$account->ig_user_id}/media", [
                'access_token' => $account->access_token,
                'image_url' => $mediaUrl,
                'caption' => $post->caption,
            ])->throw()->json();

        $this->waitUntilReady($account, $container['id']); // pollare per reel/video

        $published = Http::baseUrl('https://graph.instagram.com')
            ->post("/{$account->ig_user_id}/media_publish", [
                'access_token' => $account->access_token,
                'creation_id' => $container['id'],
            ])->throw()->json();

        $post->update([
            'status' => 'published',
            'platform_post_id' => $published['id'],
            'published_at' => now(),
        ]);
    }
}
```

Punti da non sottovalutare:

- **L'immagine deve essere raggiungibile via URL pubblico** — Meta fa una `curl` diretta su `image_url`/`video_url`, non basta un file protetto da sessione. Dato che oggi tutto resta su disco locale, serve una route pubblica firmata (es. `/media/{token}`, token temporaneo nell'URL non nell'header). Con S3/R2 in futuro: URL pre-firmati dello stesso bucket, stesso concetto.
- **Polling status** consigliato soprattutto per reel/video: `GET /{container-id}?fields=status_code` → `IN_PROGRESS`/`FINISHED`/`ERROR`/`EXPIRED`, una volta al minuto per non più di 5 minuti.
- **Rate limit: 100 post pubblicati per account in finestra mobile di 24h** (non reset a mezzanotte fisso), caroselli contati come un post singolo. Controllare quota reale prima di accodare: `GET /{ig-user-id}/content_publishing_limit?fields=quota_usage,config` — è per-account, non globale sull'app. Con più tenant in parallelo conviene una coda Laravel dedicata alla pubblicazione (separata da quella di generazione), per throttling per-`ig_user_id` senza rallentare il resto.

### Rinnovo token

Long-lived token dura 60 giorni, **niente refresh_token separato**: si rinnova richiamando lo stesso endpoint col token corrente (deve avere più di 24h di vita e non essere già scaduto). Scheduled command giornaliero:

```php
// routes/console.php
Schedule::command('instagram:refresh-tokens')->daily();
```

Cerca account con `token_expires_at` entro 10-15 giorni, chiama `GET /refresh_access_token?grant_type=ig_refresh_token&access_token={token}`. Se fallisce → stato `needs_reauth` + notifica visibile al cliente (altrimenti scopre che il personaggio ha smesso di pubblicare solo notando l'assenza di post).

### Niente SDK di terze parti

Nessun pacchetto Composer mantenuto per il nuovo flusso "Instagram Login" — meglio un service proprio (`InstagramGraphService` sopra `Http::` di Laravel) per controllare retry/rate-limit/scope senza dipendere da community package che potrebbe non stare al passo con Meta.

## 10. Decisioni confermate (da non rimettere in discussione senza motivo)

- Storage su disco dell'hosting attuale per ora, ma sempre via Storage facade Laravel organizzato per tenant/personaggio (migrazione a S3/R2 futura = cambio config, non riscrittura codice).
- Un tenant può avere più personaggi: `max_characters` nel piano regola il limite, eventuale add-on a pagamento oltre soglia.
- Multi-utente per tenant confermato fin da subito; permessi granulari per-personaggio (`character_user`) aggiungibili quando servirà davvero.
- Cashier (billing) collegato a `Tenant`, non a `User` — un tenant fattura, gli utenti sono solo persone che accedono.
- Etichette pannello admin in italiano, **eccetto "Trial"** (si usa anche in italiano, lasciato non tradotto di proposito).
- `scene_elements` con `tenant_id` nullable = pattern libreria globale condivisa + personalizzazioni per-tenant, evita di dover "ricreare tutto da zero" per ogni nuovo personaggio.
- **Baseline del mood su colonne dedicate in `character_state`** (non hardcoded, non testo libero) — decisione presa il 16 luglio proprio perché personaggi diversi (Sofia, Fernando, ...) avranno baseline diverse; modificabile a mano/tinker senza toccare lo schema.

## 11. Cervello editoriale e motore di vita — 11.1-11.7 implementati e verificati, 11.8 no

*Discussione del 16 luglio 2026. Nasce dall'osservazione che il sistema, pur essendo già intelligente dentro ogni singola pubblicazione, non lo è ancora nel decidere cosa pubblicare: la sequenza fissa `formato → argomento → notizia → testo → immagine` fa percepire il personaggio come un dipendente che riceve un incarico editoriale ogni giorno, non come una persona che vive e a volte racconta qualcosa.*

**Stato: la parte 11.1-11.6 (schema + tick deterministico del motore di vita) è implementata e verificata il 16 luglio 2026 — vedi 11.10. La parte 11.7 (cervello editoriale a una chiamata GPT) è implementata e verificata con chiamate OpenAI reali il 16 luglio 2026 — vedi 11.11, e include dal tardo pomeriggio del 16 luglio un pavimento di pubblicazione per-personaggio — vedi 11.12. La parte 11.8 (uso opzionale delle notizie) resta non implementata. ⚠️ La verifica di 11.7 ha fatto emergere un disallineamento importante con questo documento: esiste già in `~/character-engine-app` una pipeline di generazione+pubblicazione+scheduling funzionante (non solo nel vecchio prototipo PHP) che le sezioni 9 e 12-punto-1 descrivono ancora come "non implementata" — vedi 11.11 per il dettaglio, non ancora riconciliato con questo documento.**

### 11.1 Principio guida

Non si programma il personaggio dicendogli cosa pubblicare (quello resta un "incarico editoriale" anche se il contenuto è vario). Si programma invece la **fisica di un piccolo mondo interno** — mood, drives, relazioni, storyline — che evolve per conto suo, ogni giorno, indipendentemente dal fatto che oggi si pubblichi qualcosa o no. Il livello editoriale (cosa raccontare oggi) non decide la vita del personaggio: la osserva e ne traduce un frammento in contenuto. Il pubblico non vede mai lo stato interno per intero, solo frammenti — questa opacità parziale è parte di ciò che rende il personaggio "reale" quanto la varietà dei contenuti.

Due processi separati, non uno:
- **motore di vita** (tick giornaliero, quasi tutto deterministico PHP): aggiorna mood/drives/storyline/relazioni, a prescindere da cosa/se si pubblica. **Implementato.**
- **cervello editoriale** (al momento della generazione): legge lo stato già calcolato e decide, con **una sola chiamata GPT**, se e cosa pubblicare oggi. **Implementato e verificato — vedi 11.7, 11.11, 11.12.**

### 11.2 Mood come vettore, non etichetta

Invece di una stringa libera tipo `"stanca ma soddisfatta"` (che il modello dovrebbe reinventare ogni volta senza uno stato vero sotto), un piccolo vettore per personaggio, con baseline propria definita in `character_state`:

- `energia` (0-1)
- `valenza` / umore (-1..1)
- `stress` / carico (0-1)

Decadimento verso la baseline ad ogni tick, tipo molla che torna in equilibrio:

```
energia = energia + (baseline_energia - energia) * decay_rate   // decay_rate: 0.15/giorno
```

Gli eventi spostano temporaneamente il vettore (deltas definiti per evento, vedi 11.4), poi il decadimento lo riporta verso l'equilibrio nei giorni successivi. **Decisione presa in fase di implementazione**: una riga `character_state` nuova viene creata con `mood_* = baseline_*` (personaggio "a riposo" fin dal primo giorno), non a 0 — vedi 11.10 per il bug iniziale legato a questo punto e come è stato corretto.

### 11.3 Storyline: ciclo di vita

Stati: `dormiente` → `aperta` → (`in pausa`) → `risolta` | `abbandonata`.

Pressione a riprendere una storyline aperta, calcolata senza GPT:

```
pressione = importanza * min(1, giorni_da_ultimo_aggiornamento / 10)
```

Verificato in tinker (vedi 11.10): con `last_updated_at` forzato a 10 giorni fa e `importance = 0.5`, il tick stampa correttamente pressione 0.5, e **il solo calcolo/stampa della pressione non aggiorna `last_updated_at`** — l'orologio della storyline avanza solo quando succede davvero qualcosa che la riguarda, non per il fatto di essere osservata da un tick.

Regola scomoda ma realistica: se una storyline resta aperta troppo a lungo senza che nessuno se ne occupi (soglia attuale: 35 giorni, in `config/life_engine.php`), va marcata automaticamente `abbandonata`, non `risolta` — le persone vere lasciano cadere le cose senza un post riassuntivo di chiusura. Applicato solo allo stato `aperta` (una storyline `in_pausa` non viene punita dal solo passare del tempo).

Nuove storyline nascono o da eventi di vita sufficientemente importanti (vedi 11.4) o vengono seminate a mano.

### 11.4 Generazione di eventi di vita (usa `life_events`/`timeline_entries` già esistenti)

Tick giornaliero per personaggio: probabilità di "oggi succede qualcosa" = `base + (1-base) * min(1, giorni_da_ultimo_evento / stall_window)`, con `base = 0.2` e `stall_window = 14` giorni in config. Se il tiro va a buon fine: pesca un evento dal pool `life_events` rispettando `weight` e `cooldown_days` (già in schema), applica i suoi effetti su mood (ed eventualmente su una relazione, vedi 11.5), crea una `timeline_entries`.

**Nota aperta, non ancora applicata**: con questa formula la probabilità arriva esattamente al 100% dopo `stall_window` giorni senza eventi, il che la rende di nuovo deterministica oltre quella soglia. Da valutare se abbassare il tetto (es. 0.85-0.9) per restare probabilistici anche nella coda lunga.

Solo per la descrizione in linguaggio naturale dell'evento (non per la decisione di generarlo) servirà un tocco di LLM — punto di aggancio già pronto: l'evento Laravel `App\Events\LifeEventOccurred`, dispatched dopo ogni `TimelineEntry` creata dal tick, **zero listener registrati** finché non si implementa la generazione batch del testo (idealmente un'unica chiamata condivisa per tutti gli eventi nati quella notte, non una per evento).

### 11.5 Relazioni

Tabella `character_relationships`: personaggio, nome della persona/relazione, tipo, sentiment (-1..1), ultima interazione. Aggiornata dagli eventi che la toccano (via `target_relationship` su `life_events` e chiave opzionale `relationship_sentiment` dentro `mood_deltas`, default 0.1 se assente); anche qui il sentiment rilassa verso neutro nel tempo se non succede nulla di nuovo.

### 11.6 Drives

Nessun tick separato: si aggiornano deterministicamente guardando la cronologia reale già presente in `posts`/`generations` (`recent_count`, `days_since_last` per categoria, salvati come json su `character_state.drives`). Puro conteggio SQL, zero chiamate GPT.

### 11.7 Il cervello editoriale (una sola chiamata GPT per generazione) — implementato e verificato il 16 luglio 2026

Tre pezzi, in `app/Services/` + un comando di test, deliberatamente **isolati** dalla pipeline di generazione/pubblicazione già esistente (vedi nota in 11.11 — non integrati/riconciliati, per scelta, in questa sessione):

- **`EditorialContextBuilder`** — puramente deterministico, nessuna chiamata esterna. Assembla: bible (riusa la stessa lista di sezioni già curata in `config/purposes.php` per `instagram_post`), profilo visivo, mood/drives da `character_state`, le 3 storyline `aperta`/`in_pausa` con pressione più alta (`Storyline::pressure()`), le 5 relazioni più di recente aggiornate, gli ultimi 12 post e le ultime 15 `timeline_entries`.
- **`EditorialBrainService`** — **una sola chiamata** `Http::` (niente SDK, stesso principio di `OpenAiTextService`/sezione 9) a `/v1/chat/completions` con **Structured Outputs** (`response_format: json_schema`, `strict: true`), che garantisce un JSON sempre conforme a: `decisione` (`pubblica`/`non_pubblicare`), `motivazione`, `formato` (`post`/`carousel`/`reel`/`story`/null), `idea`, `testo`, `prompt_immagine`, `storyline_da_aggiornare` (`{storyline_id, nuovo_stato, note}` o null), `used_news` (sempre `false` per ora, 11.8 non collegato). Dal 16 luglio pomeriggio lo schema è costruito dinamicamente per supportare il pavimento di pubblicazione — vedi 11.12.
- **`EditorialCycleService`** — orchestratore: crea `generations` (`purpose: editorial_decision`), chiama il cervello, crea `editorial_decisions`, aggiorna la storyline referenziata (**solo** se il modello la nomina esplicitamente — `last_updated_at` si muove solo per un evento narrativo reale, stessa regola già verificata in 11.3/11.10), crea un `post` in stato `draft` **solo se** `decisione = pubblica` (nessuna immagine generata, nessun credito scalato, nessuna pubblicazione — tutto fuori scope). Se la chiamata OpenAI fallisce o il JSON non valida: `generation` marcata `failed`, nessun'altra riga toccata.
- **`character:run-editorial-cycle {character}`** — comando di test manuale, stampa la decisione JSON completa. **Non agganciato a `routes/console.php`**: ogni esecuzione costa una vera chiamata OpenAI, va lanciato a mano finché la qualità delle decisioni non è stata rivista.

Nel prompt, lo stato interno (mood/drives) è passato come JSON con un'istruzione esplicita: non deve mai trapelare come numero o terminologia tecnica nel testo pubblicato, solo come sfumatura di tono. Dettagli di verifica ed eventuali correzioni in 11.11.

Principio di costo: **l'intelligenza sta nell'architettura (stato già calcolato, filtri deterministici), non nel numero di chiamate**. Target raggiunto: 1 chiamata GPT per ciclo editoriale, a prescindere dall'esito — anche una decisione "non_pubblicare" costa una chiamata vera (nessun modo di saperlo prima di chiamare). Non ancora implementato: 1 chiamata batch condivisa a notte per il colore degli eventi nuovi (11.4), e l'idea di un'unica chiamata di pianificazione al mattino che prepara 2-3 idee pronte per il giorno.

### 11.8 Le notizie sono uno strumento opzionale, non un passaggio obbligatorio — NON ANCORA IMPLEMENTATO

`NewsData` andrà interrogato solo per la frazione di contenuti che ne ha davvero bisogno (stima iniziale 20-30%), con la decisione se serve o no presa da Laravel prima di generare.

### 11.9 Schema dati aggiuntivo — implementato il 16 luglio 2026

- `character_state` (1:1 con `characters`): `mood_energia`, `mood_valenza`, `mood_stress`, `baseline_energia`, `baseline_valenza`, `baseline_stress` (float), `drives` (json), `last_tick_at`
- `storylines`: `id, character_id, title, status (dormiente/aperta/in_pausa/risolta/abbandonata), importance (float), started_at, last_updated_at, resolution_notes`
- `timeline_entries`: colonna `storyline_id` (nullable) aggiunta
- `life_events`: colonne `mood_deltas` (json), `can_start_storyline` (bool), `target_relationship` (nullable) aggiunte
- `character_relationships`: `id, character_id, name, type, sentiment (float), last_interaction_at, notes`
- `editorial_decisions`: `id, generation_id, decisione, motivazione, storyline_id (nullable), used_news (bool)` — popolata da 11.7
- `character_editorial_settings` — aggiunta 16 luglio pomeriggio (11.12), 1:1 con `characters` come `character_state`: `max_giorni_silenzio` (int, default 2), pensata per crescere con altre impostazioni editoriali per-personaggio (in futuro: cadenza commerciale minima, brief sponsor — vedi nota di discussione in 11.12)

Modelli, comando `character:live-tick {character?}`, schedulazione (`routes/console.php`, `->daily()`, orario da tarare) e config in `config/life_engine.php` (decay_rate, finestra pressione storyline, soglia abbandono, probabilità base evento, finestra drives) — tutto in `~/character-engine-app`.

### 11.10 Note di implementazione e verifica (16 luglio 2026)

Durante la verifica manuale in tinker sono emersi e risolti due problemi reali, non solo di dettaglio:

1. **Segno invertito nel calcolo Carbon dei `days_since_last`** (dentro `drives`): risultavano tutti negativi (es. -3.29, -8.29) invece che positivi. Corretto; riverificato in tinker (`dd(CharacterState::first()->drives)`) → valori ora positivi (es. 3, 0). Da tenere a mente per qualsiasi altro calcolo di differenza date nel motore di vita: verificare sempre con un caso reale, non fidarsi della sola lettura del codice.
2. **Il mood non si muoveva dalla creazione** — al primo tick su un personaggio nuovo, `mood_energia/valenza/stress` restavano a 0 invece di partire dalla baseline o decadere verso di essa. Deciso e corretto: `character_state` viene creato con `mood_* = baseline_*`. Riverificato con due tick consecutivi dopo un evento (partenza 0.65/0.15/0.35 → atteso 0.636125/0.108375/0.336125 con `decay_rate 0.15` → numeri del dump coincidono esattamente).
3. Verificato anche che il calcolo della pressione delle storyline (11.3) **non ha lo stesso bug di segno** e **non tocca `last_updated_at` per il solo fatto di essere calcolato** — solo un evento narrativo reale deve aggiornare quella data.

Nessun problema aperto blocca il proseguimento; resta solo la nota su probability cap in 11.4 (cosmetica/di tuning, non un bug).

### 11.11 Note di implementazione e verifica del cervello editoriale (16 luglio 2026)

**Disallineamento scoperto prima di iniziare** (da leggere prima di toccare 11.7/12.1 in futuro): ispezionando `app/Console/Commands/` e `routes/console.php` prima di scrivere codice è emerso che esiste già, funzionante, una pipeline di generazione+pubblicazione+scheduling che questo documento (sezioni 9 e 12 punto 1) segnava ancora come "non implementata":
- `app/Jobs/GenerateInstagramPostJob.php` (+ `GenerateNewsStoryJob.php`) — pipeline completa: sceglie un `life_event` (`LifeEventSelector`), testo via `OpenAiTextService`/`PromptBuilder`, immagine via `FalImageService`/`ImagePromptBuilder`/`ReferenceImageSelector`, crea `TimelineEntry` + `Post` draft. È esattamente la sequenza fissa "formato→argomento→notizia→testo→immagine" descritta in apertura di sezione 11 come il problema da superare — ma gira già, senza sapere nulla di mood/storyline/drives.
- `app/Jobs/PublishInstagramPostJob.php` + `app/Services/InstagramGraphService.php` (chiamate reali a `api.instagram.com`, non un placeholder) + `InstagramAuthController.php` — pubblicazione Instagram implementata, non solo disegnata come dice la sezione 9.
- Un sistema di scheduling settimanale non documentato altrove: `WeeklyScheduleSlot`/`ScheduledPost` (model + migrazioni + Filament resource), `MaterializeWeeklySchedule`/`ProcessDueScheduledPosts`→`ProcessScheduledPostJob`, già agganciato in `routes/console.php` (`schedule:materialize-weekly` giornaliero, `schedule:process-due` ogni 15 minuti).
- `ReplyToInstagramComments`/`CommentReply` — risposta automatica ai commenti, schedulata oraria.

**Decisione presa per questa sessione**: costruire il cervello editoriale **isolato**, senza toccare né riconciliare questa pipeline esistente. I due percorsi restano paralleli e scollegati — la riconciliazione (far passare la pipeline reale attraverso `EditorialCycleService` invece che attraverso la scelta fissa di `LifeEventSelector`) resta un passo esplicitamente da fare, non ancora pianificato in dettaglio. **Le sezioni 9 e 12 punto 1 di questo documento non sono state riscritte e restano quindi non accurate sullo stato del codice** — da correggere quando si affronta la riconciliazione.

**Implementazione**: `EditorialContextBuilder`, `EditorialBrainService`, `EditorialCycleService`, comando `character:run-editorial-cycle {character}` (dettagli in 11.7). Il prompt reale generato per Sofia è risultato ~46.800 caratteri, quasi interamente dal dump delle 13 sezioni bible già usate da `PromptBuilder` per i post — scelta deliberata di riuso invece di curare una lista più corta, da rivedere se in futuro risultasse un problema di costo/qualità (non emerso nei test).

**Verificato con chiamate OpenAI reali** (credenziali presenti nell'ambiente) su Sofia, manipolando `character_state` in tinker come già fatto per il motore di vita:
1. Stato normale → `pubblica`, formato `reel` su "Progetto con Fernando": `generation`/`editorial_decision`/`post` draft creati coerentemente, storyline aggiornata (`last_updated_at` a ora, `resolution_notes` popolata, resta `aperta`), nessun numero di stato interno trapelato nel testo.
2. Subito dopo, stesso personaggio → ancora `pubblica` ma formato `story` su un episodio diverso, esplicitamente per non ripetere i contenuti appena pubblicati.
3. Mood forzato a esausta/stressata (`energia 0.1, valenza -0.3, stress 0.9`) → `non_pubblicare`, **nessun post creato** (conteggio verificato invariato), motivazione coerente.
4. Successivamente, 4 run consecutivi nello stesso pomeriggio con stato realistico (non forzato) → 4× `non_pubblicare` di fila, motivazione coerente e stabile ogni volta (contenuti recenti già saturi su Fernando/quotidianità) — nessuna oscillazione casuale tra run ravvicinate. Un `character:live-tick` successivo ha prodotto un evento nuovo ("Giornata con un cliente"), e il ciclo editoriale successivo è tornato a `pubblica` (carousel), confermando che il modello trova materiale fresco quando c'è davvero, non pubblica per inerzia. Questo test ha reso concreto il problema poi risolto in 11.12: 4 giorni di silenzio non sono accettabili per un prodotto commerciale, anche se narrativamente corretti.

**Due bug reali corretti durante la verifica** (stessa famiglia del punto 1 di 11.10 — Carbon 3 e i tipi restituiti dai diff di date, da tenere a mente ovunque nel motore di vita/cervello editoriale):
1. `Storyline::daysSinceLastUpdate()` dichiarava `: ?int` ma `diffInDays()` restituisce `float`: deprecation warning silenzioso per troncamento implicito. Corretto con cast esplicito `(int)`.
2. `EditorialContextBuilder::buildRecentTimeline()` mandava al prompt righe tipo `"0.041871465034722 giorni fa"` invece di `"0 giorni fa"` — stesso problema, non passava dal metodo già corretto sopra. Corretto con lo stesso cast.

### 11.12 Pavimento di pubblicazione per-personaggio (16 luglio 2026, tardo pomeriggio)

**Problema**: il test 4 di 11.11 (4× `non_pubblicare` di fila) ha reso concreto che senza un limite il cervello editoriale può restare in silenzio troppo a lungo — narrativamente corretto, commercialmente non accettabile per un cliente che paga un piano con una cadenza minima. Ogni ciclo, `pubblica` o `non_pubblicare` che sia, costa comunque una vera chiamata OpenAI: il problema non è il costo (qualche centesimo a chiamata, anche con molti giorni di silenzio di fila), è che il modello va guidato con una regola deterministica invece che sperare rispetti un'istruzione testuale.

**Decisione di design esplicita**: questo NON deve essere legato a Sofia — ogni personaggio, di ogni tenant, ha una propria soglia. Da qui `character_editorial_settings` invece di una costante in config.

**Implementazione**:
- `character_editorial_settings` (1:1 con `characters`, stesso pattern di `character_state`/`character_visual_profile`): `max_giorni_silenzio` (int, default 2), `firstOrCreate` automatico se manca — non blocca mai per assenza di riga. Pensata come punto di crescita per future impostazioni editoriali per-personaggio (es. la cadenza commerciale minima di cui abbiamo discusso separatamente, non ancora costruita).
- `EditorialCycleService` calcola i giorni dall'ultimo `Post` creato per il personaggio (da `created_at`, non `published_at` — la pubblicazione Instagram reale non è collegata a questo percorso, quindi `published_at` sarebbe sempre nullo nei test; **da rivedere quando la pubblicazione sarà collegata**, sezione 12 punto 1). Nessun post mai creato conta come silenzio "infinito" (supera qualunque soglia finita).
- Se i giorni superano `max_giorni_silenzio`: **lo schema JSON strutturato della chiamata OpenAI restringe l'enum di `decisione` alla sola `"pubblica"`** (Structured Outputs, `strict: true`) — è lo schema stesso a rendere impossibile un'altra risposta, non il testo del prompt a "convincere" il modello. Aggiunto anche un paragrafo esplicito nel prompt che spiega perché pubblicare non è opzionale oggi, lasciando comunque al modello la libertà di trovare l'idea più onesta e meno forzata (anche una story minimale va bene).
- Filament: nuovo relation manager "Impostazioni editoriali" sulla scheda personaggio (stesso pattern di `VisualProfileRelationManager`), campo `max_giorni_silenzio` modificabile.

**Verificato con chiamate OpenAI reali** su Sofia:
1. Soglia 2gg, ultimo post forzato a 3gg fa → `pubblica`. Ripetuto con lo **stesso identico stato di mood esausto/stressato** che nel test 3 di 11.11 aveva dato `non_pubblicare` in modalità libera → stavolta `pubblica`, con un'idea onesta e minimale (un pensiero breve sul caffè, non un contenuto forzato) — prova che è lo schema a bloccare l'opzione, non solo la persuasione testuale del prompt.
2. Stesso personaggio, ultimo post riportato a oggi (sotto soglia) → tornato `non_pubblicare` liberamente, comportamento libero invariato.
3. `max_giorni_silenzio` alzato a 5 per Sofia, stessa situazione reale di 3 giorni di silenzio (ora sotto la nuova soglia) → tornato `non_pubblicare` — stessa realtà, esito opposto solo perché la soglia in DB per questo personaggio è cambiata: prova che il valore è letto per-personaggio, non hardcoded.

Nessun caso limite "il modello non trova nessuna idea nemmeno forzato" incontrato nei test. Pannello Filament verificato non rotto dopo la modifica (redirect 302 normale su `/aiadmin/characters`, nessun errore nuovo in log). Non toccato: Instagram, pipeline esistente, scheduling, livello commerciale (brief sponsor — discusso ma non ancora costruito, note nella conversazione del 16 luglio: i guadagni reali degli influencer virtuali oggi sul mercato vengono quasi tutti da sponsorizzazioni/ambassadorship, non da un personaggio che "vive per sé"; l'autenticità costruita in sezione 11 è la precondizione per rendere credibili quelle sponsorizzazioni, non un'alternativa ad esse — `character_editorial_settings` è il punto dove agganceremo, in una sessione a parte, un brief commerciale con urgenza legata a scadenze di campagna, sullo stesso principio della pressione delle storyline).

### 11.13 Prima pubblicazione reale end-to-end — riuscita (16-17 luglio 2026)

Per "non sprecare" la chiamata OpenAI di uno dei cicli editoriali di test, pubblicato per davvero su Instagram uno dei `Post` draft generati dal cervello editoriale (Post#13), riusando la pipeline immagine/pubblicazione già esistente (`GenerateInstagramPostJob`/`ImagePromptBuilder`/`FalImageService`/`PublishInstagramPostJob`/`InstagramGraphService` — sezione 11.11), non l'`EditorialCycleService` isolato. **Prima pubblicazione reale di tutta la sessione** — chiude simbolicamente il cerchio partito dalla riflessione iniziale sul cervello editoriale: un contenuto deciso da mood/drives/storyline, non da un incarico editoriale fisso, ora vive davvero su Instagram.

**Risultato**: pubblicato con successo — https://www.instagram.com/p/Da3GZUzESH9/, `platform_post_id` 18402311914155024, ore 16:45:17 del 16 luglio 2026, `media_type: IMAGE`, confermato interrogando l'API Instagram direttamente (non solo il DB locale).

**Gap reale scoperto e diagnosi precisa (più specifica di quanto pensassi inizialmente)**: `EditorialBrainService` produce un solo `prompt_immagine` per contenuto, non un prompt per slide — ma il problema non è solo "manca un array". La pipeline immagine esistente genera i carousel con `ImageTextOverlayService`, pensato per overlay di **8-10 parole** (quote-card), mentre le "Slide N" nella caption generate dal cervello editoriale sono paragrafi narrativi da 50-100+ parole — un formato incompatibile con l'overlay per costruzione, non solo per assenza di un secondo prompt immagine. Forzarlo avrebbe prodotto testo che trabocca dall'immagine. **Scelta pragmatica presa**: pubblicato come post a immagine singola invece che come carousel, usando l'unico `prompt_immagine` per la cover (`Post#13.media_type` cambiato da `carousel` a `image`). Da risolvere quando si affronterà la riconciliazione (sezione 12 punto 1): o si costruisce un vero motore multi-immagine per i carousel del cervello editoriale, o gli si istruisce di non proporre mai `carousel` finché quella parte non esiste, o si adatta lo stile del testo carousel del cervello editoriale al formato quote-card che l'overlay esistente sa già gestire.

**Diagnosi del blocco ngrok (dalla sessione precedente) e soluzione**: causa reale non applicativa — tunnel ngrok (`waking-dispense-uniformed.ngrok-free.dev`, configurato in `APP_URL`) non attivo, Meta riceveva la pagina "tunnel offline" al posto dell'immagine. Nessuno script/processo ngrok era stabilito nel progetto (solo l'authtoken in `~/.config/ngrok/ngrok.yml`); avviato manualmente `ngrok http 80` (porta confermata da `sail ps`, non assunta). **Il dominio pubblico assegnato è risultato identico a quello già configurato** — probabile dominio statico gratuito già assegnato all'account ngrok (disponibile gratuitamente dal 2023), da confermare sulla dashboard ngrok. Se confermato, la preoccupazione iniziale ("i domini gratuiti cambiano ad ogni riavvio") non si applica a questo account: `APP_URL` può restare stabile tra un riavvio e l'altro del tunnel, nessun piano a pagamento necessario per questo. Verificato con `curl` diretto che l'immagine rispondeva `200 image/png` prima di ritentare la pubblicazione (un solo tentativo, riuscito).

**Nota operativa**: il tunnel ngrok è rimasto attivo in background dopo la sessione (non collegato a nessun processo Sail — va fermato a mano quando non serve, es. `pkill ngrok`). Finché resta attivo, l'app locale (pannello admin incluso) è raggiungibile pubblicamente tramite quell'URL — da spegnere quando non si stanno facendo verifiche di pubblicazione, non da lasciare acceso indefinitamente per igiene di sicurezza minima.

### 11.14 Riconciliazione tra il cervello editoriale e la pipeline esistente — fatta (17 luglio 2026)

**Obiettivo**: far sì che i cicli automatici di `ProcessScheduledPostJob` (scheduling settimanale, nessuna revisione umana nel mezzo) usino il cervello editoriale invece della selezione fissa di `LifeEventSelector`, senza rompere i percorsi manuali/di test esistenti.

**Fase 0 — investigazione prima di toccare nulla** (regola di sicurezza già seguita in 11.11): verificato che non esiste, dentro il progetto (WSL/cron/supervisor), alcun trigger automatico oltre a `schedule:process-due` (ogni 15 minuti) già noto e documentato in 11.11. **Punto cieco esplicito, non risolto**: da WSL non è possibile escludere con certezza che il Task Scheduler di Windows lanci qualcosa a livello di sistema operativo, fuori da WSL — ritenuto improbabile ma non verificabile da qui. Da tenere presente prima di fidarsi ciecamente del sistema in produzione.

**Decisione architetturale**: nessuna riscrittura dei Job esistenti. `GenerateInstagramPostJob` è stato esteso con un flag `useEditorialBrain` (default `false`) che fa biforcare `handle()` in due percorsi interni:
- `handleLegacyFlow(...)` — la pipeline originale, byte-identica, usata quando si passa `forceLifeEventId`, in modalità manuale, e da tutti i comandi `Test*`.
- `handleEditorialBrainFlow(...)` — nuovo: chiama `EditorialCycleService::run($character)`; se non c'è nessuna decisione lancia eccezione (errore reale); se la decisione è `non_pubblicare` ritorna `null` (nessun post, esito legittimo, non un fallimento); altrimenti sceglie un'immagine di riferimento, genera l'immagine via `FalImageService` usando `prompt_immagine` del cervello editoriale, salva il file, crea una `TimelineEntry` collegata alla storyline della decisione, aggiorna `media_urls` del post e l'`output` della generation. Eventuali eccezioni in questa fase marcano come `failed` il `Post`, non la `Generation` — perché la decisione editoriale in sé resta valida anche se poi la generazione immagine fallisce.
- `handle()` ora ritorna `?Post` (prima `void`) — necessario per distinguere "nessun post perché non_pubblicare" da "nessun post perché fallito".

`ProcessScheduledPostJob` modificato: il ramo `story` resta invariato; il ramo `manual` ora usa `Bus::dispatchNow(...)` invece di `::dispatchSync()` (vedi bug sotto) con controllo esplicito su risultato nullo (`RuntimeException` se manca); nuovo ramo automatico/casuale che chiama `GenerateInstagramPostJob` con `useEditorialBrain: true` — se il risultato è `null` (non_pubblicare legittimo), lo `ScheduledPost` viene marcato con un nuovo stato `skipped` invece di forzare comunque una pubblicazione, e il job si ferma lì (nessun tentativo di pubblicare nulla). Altrimenti procede come prima: `generated` → `PublishInstagramPostJob::dispatchSync()` → `published`, **ancora nessuno step di revisione umana tra generazione e pubblicazione automatica** — comportamento preesistente, confermato e non modificato in questa sessione.

Nuovo stato `skipped` aggiunto anche al pannello Filament (`ScheduledPostsTable`): colore grigio, etichetta "Saltato (non pubblicare)", stesso pattern già usato per `cancelled`.

**Bug reale trovato e corretto**: `::dispatchSync()` su un Job `ShouldQueue` esegue gli effetti collaterali (il post viene creato nel DB) ma **non restituisce in modo affidabile il valore di ritorno di `handle()`** al chiamante — passa da `SyncQueue::push()`, che scarta il valore. Confermato leggendo il codice sorgente di Laravel e con un test reale (post creato correttamente nel DB, ma il chiamante riceveva `null`). Questo era critico perché la nuova logica deve distinguere "nessun post per non_pubblicare" da "nessun post per un fallimento reale" — la vecchia soluzione (interrogare "l'ultimo post in bozza") non permetteva questa distinzione in modo affidabile. Corretto sostituendo con `Bus::dispatchNow(new Job(...))` nei due punti di `ProcessScheduledPostJob` che dipendono dal valore di ritorno. Riverificato funzionante dopo la modifica.

**Carousel disabilitato a livello di schema**: coerentemente con la scoperta di 11.13 (incompatibilità strutturale tra `ImageTextOverlayService`, pensato per overlay quote-card di 8-10 parole, e il testo narrativo lungo delle slide del cervello editoriale), l'enum `formato` dello schema JSON strutturato in `EditorialBrainService` è stato ristretto a `['post', 'reel', 'story', null]` — `carousel` rimosso non solo dal prompt ma dallo schema stesso (stessa filosofia già usata per il pavimento di pubblicazione in 11.12: è lo schema, non l'istruzione testuale, a rendere impossibile la scelta). Resta da fare, quando si deciderà di riabilitarlo: un vero motore multi-immagine, oppure uno stile di testo carousel compatibile con l'overlay esistente.

**Verificato con esecuzioni reali** (4 test): (1) ciclo automatico con stato che porta a `pubblica` → post generato, immagine creata, `TimelineEntry` collegata alla storyline corretta, `ScheduledPost` marcato `published`; (2) ciclo automatico con stato che porta a `non_pubblicare` → nessun post creato, `ScheduledPost` marcato correttamente `skipped`, nessun tentativo di pubblicazione; (3) percorso manuale (`mode === 'manual'`) riverificato invariato dopo il passaggio a `Bus::dispatchNow`; (4) percorsi legacy (`Test*`, `forceLifeEventId`) riverificati byte-identici, non toccati dalla modifica. **Deliberatamente non eseguita** una seconda pubblicazione reale su Instagram per questo percorso specifico — le prove indirette (post creato correttamente, immagine generata e salvata, `PublishInstagramPostJob` già verificato funzionante in 11.13 con lo stesso identico servizio sottostante) sono state giudicate sufficienti, per non consumare un'altra chiamata OpenAI/Fal solo per la conferma finale.

**Gap `ScheduledPost.instructions` non passato al cervello editoriale — risolto lo stesso giorno**: per gli slot automatici/casuali, il campo `instructions` (istruzioni specifiche del giorno, es. "oggi parla del suo caffè preferito al bar") veniva ignorato nel percorso `useEditorialBrain`. Corretto aggiungendo `?string $instructions = null` in coda alla stessa firma già usata per `forcePublish`/`daysSinceLastPost`/`maxGiorniSilenzio`, propagato `EditorialCycleService::run()` → `EditorialBrainService::decide()`/`lastPrompt()`: quando valorizzato, il prompt include una sezione `=== ISTRUZIONI SPECIFICHE PER OGGI (dal pannello di programmazione) ===` — guida il **contenuto**, non forza la pubblicazione (quel meccanismo resta solo il pavimento di silenzio, 11.12), stesso principio già in uso in `PromptBuilder::buildInstructionsSection()` per la pipeline legacy. `GenerateInstagramPostJob::handleEditorialBrainFlow()` passa `$this->instructions`; `ProcessScheduledPostJob` (ramo automatico) passa `$scheduledPost->instructions`.

Verificato con chiamate OpenAI reali, tre casi: (1) istruzioni specifiche + stato che porta a `pubblica` → contenuto che riflette davvero l'istruzione (non solo mood/drives/storyline come prima); (2) stesso ciclo con `instructions = null` → comportamento identico a prima, nessuna sezione istruzioni nel prompt (nessuna regressione, confermato anche ispezionando il prompt renderizzato); (3) istruzioni **e** pavimento di pubblicazione superato insieme → `decisione: pubblica` forzata dallo schema, motivazione che cita esplicitamente entrambi i meccanismi senza conflitto, contenuto comunque guidato dall'istruzione.

**Non toccato in questa sessione**: `GenerateNewsStoryJob`/il ramo `story`, `ReplyToInstagramComments`, `InstagramGraphService`, `MaterializeWeeklySchedule`, il livello commerciale, la logica crediti, `carousel` (resta disabilitato a livello di schema).

**Commit effettuati** (in Claude Code): riconciliazione + pavimento di pubblicazione rimasto in sospeso dalla sessione precedente separati in due commit distinti per rispecchiare l'ordine reale del lavoro, poi un terzo commit per il fix di `instructions`.

### 11.15 Carousel riabilitato nel cervello editoriale — fatto (17 luglio 2026)

**Gap chiuso** (aperto in 11.13, lasciato esplicitamente irrisolto in 11.14/12.4): il cervello editoriale non poteva più proporre `formato: carousel` perché `ImageTextOverlayService` (overlay quote-card da 8-10 parole) è strutturalmente incompatibile con le idee narrative lunghe che il servizio scriveva.

**Scelta di design** (una delle tre opzioni aperte in 12.4, non un motore multi-immagine): far scrivere al cervello editoriale frasi brevi in stile "quote card" — stesso principio già usato con successo dalla pipeline legacy (`PromptBuilder`/`carousel_slides`). Riusa `ImageTextOverlayService`/`GenerateInstagramPostJob::buildCarouselSlides()` esistenti così come sono: **una sola immagine generata** (nessun costo Fal.ai aggiuntivo rispetto a un post singolo), testo diverso sovrapposto per slide.

**Implementazione**:
- `EditorialBrainService`: `carousel` riammesso nell'enum `formato`; nuovo campo `carousel_slides` nello schema strutturato (`array|null` di stringhe, sempre in `required` come gli altri campi opzionali). Prompt aggiornato con la regola quote-card (3-5 frasi, 8-10 parole, niente hashtag/emoji nelle frasi, `testo` resta la caption normale e distinta dalle slide — non deve ripeterne il contenuto). Validazione difensiva: se `decisione: pubblica` e `formato: carousel` con meno di 2 `carousel_slides`, eccezione esplicita — stesso principio "non forzare un carousel incompleto" già applicato manualmente in 11.13, ora automatico.
- `GenerateInstagramPostJob::handleEditorialBrainFlow()`: riceve ora anche `ImageTextOverlayService`; se `formato === 'carousel'` chiama `buildCarouselSlides()` (lo stesso metodo privato già usato dal percorso legacy, non duplicato) invece di salvare una singola immagine.
- `EditorialCycleService`: nessuna modifica necessaria — `mapFormatoToMediaType()` passava già `'carousel'` invariato.

**Verificato con chiamate OpenAI/fal.ai reali**: (1) decisione con `instructions` che spingono verso un carousel ("consigli pratici su come sopravvivere a una giornata di debug") → `formato: carousel`, 5 `carousel_slides` brevi e coerenti, `testo` distinto e non ripetitivo; (2) flusso completo via `GenerateInstagramPostJob` → post creato con `media_type: carousel`, 5 immagini reali generate (stessa foto base, overlay diversi), `media_urls` e `generation.output.media_paths` coerenti; (3) ispezione visiva di una slide: testo leggibile, ben centrato, velo scuro applicato per contrasto — qualità in linea con lo stile quote-card della pipeline legacy.

**Trovato ma non toccato** (fuori scope, solo segnalato): warning di deprecazione PHP 8.5 su `imagedestroy()` (no-op da PHP 8.0) in `ImageTextOverlayService.php:44`, emerso testando ripetutamente l'overlay — innocuo, non un bug funzionale.

**Non toccato**: livello commerciale, logica crediti, `GenerateNewsStoryJob`, motore multi-immagine vero (scartato come opzione per questo fix, resta disponibile come alternativa futura se lo stile quote-card si rivelasse insufficiente).

### 11.16 Fase 2 — nuovi formati "diario" e "oggetto", più un fix di coerenza visiva urgente (21 luglio 2026)

**Obiettivo**: due nuovi formati per il cervello editoriale. "oggetto" — un dettaglio della vita del personaggio, senza il personaggio stesso in scena, dedotto dalla sua documentazione (mai un esempio hardcoded nel codice, sistema multi-tenant: lo stesso prompt gira per qualunque personaggio). "diario" — un pensiero breve e privato, centellinato con una cadenza deterministica (non lasciata al modello), stesso principio del pavimento di pubblicazione (11.12): è lo schema, non un'istruzione testuale, a impedire l'abuso.

**Stato del working tree trovato a inizio sessione — non ipotetico, verificato prima di applicare qualunque patch**: gran parte del lavoro risultava già scritto e non committato, mescolato con un lavoro parallelo e indipendente sul motore di memoria narrativa (anch'esso non committato). Tre bug reali e bloccanti trovati leggendo il codice reale, non nel documento di patch:
1. `EditorialContextBuilder::build()` chiamava sei metodi (`buildBible`, `buildVisualProfile`, `buildInternalState`, `buildRelevantStorylines`, `buildRelevantRelationships`, `buildRecentTimeline`) che erano stati cancellati e sostituiti da un commento placeholder — errore fatale su **qualunque** chiamata, non solo sui formati nuovi. Ricostruiti dalla versione committata, mantenendo sopra le aggiunte reali del motore di memoria (`possibili_ricordi`, `MemoryRecallSelector` iniettato nel costruttore).
2. `EditorialBrainService::buildPrompt()` aveva subito la stessa sorte: l'intero corpo del template (circa 60 righe) sostituito da un commento placeholder, con variabili come `$istruzioniPubblicazione` mai calcolate prima dell'uso. Ricostruito allo stesso modo.
3. `EditorialCycleService::mapFormatoToMediaType()` era dichiarato due volte nella stessa classe (redeclare fatale) — rimossa la versione vecchia, mantenuta quella con la mappatura diario/oggetto → image.

Inoltre, gli esempi concreti hardcoded ("la SUA tazza, la SUA scrivania") che il documento di patch descriveva come già rimossi in una revisione precedente erano in realtà ancora presenti nel codice reale (sia nel bullet del prompt sia in `describeFormatOptions()`) — rimossi per allinearli al principio esplicito del progetto: nessun esempio fisso nel codice condiviso multi-tenant, il dettaglio deve emergere dalla bible/profilo visivo di quel personaggio specifico. Una migration (`add_narrative_format_to_posts_table`) esisteva ma aveva `up()`/`down()` vuoti (mai eseguita, colonna mai creata) — completata prima di lanciarla.

**Implementazione** (una volta risolti i blocchi sopra, il resto ha seguito il documento di patch senza scostamenti sostanziali):
- Migration: `posts.narrative_format` (nullable, dopo `generation_id`) e `character_editorial_settings.diario_ogni_n_post` (unsigned int, default 20). `Post::$fillable` aggiornato (mancava anche `media_type`, gap preesistente scoperto nello stesso file: senza, `Post::create()` scartava silenziosamente il valore passato da `EditorialCycleService` fin da quando la colonna fu introdotta).
- `EditorialBrainService`: `formatoEnum(bool $diarioDisponibile)`/`describeFormatOptions(bool $diarioDisponibile)` nuovi; `$diarioDisponibile` aggiunto **in fondo** alle firme di `decide()`/`lastPrompt()`/`buildPrompt()` (default `true`), per non rompere chiamate posizionali esistenti — stesso principio prudente già seguito in 11.14 per `useEditorialBrain`.
- `EditorialCycleService::run()`: calcola `$diarioDisponibile` contando i post creati dopo l'ultimo con `narrative_format = 'diario'` (mai fatto un diario → sempre disponibile, stesso stile con cui si calcola già `$forcePublish`); salva `narrative_format` su ogni post creato; `mapFormatoToMediaType()` mappa `post`/`diario`/`oggetto` → `image` (carousel/reel/story restano distinti).
- `EditorialContextBuilder::buildRecentPosts()`: usa `$p->narrative_format ?? $p->media_type`, così il cervello vede il formato narrativo reale dei contenuti recenti anche dopo la mappatura a `image`.
- `FalImageService::generateStandalone()`: nuovo metodo, `generate()` esistente intatto. `generate()` chiama `fal-ai/ideogram/character`, pensato per **preservare** un personaggio da una reference — sbagliato per "oggetto" (niente persone in scena) sia perché il terzo parametro non è nullable, sia perché usare quel modello con l'istruzione opposta ("nessuna persona") significherebbe combattere lo scopo per cui è stato scelto. `generateStandalone()` usa invece `fal-ai/flux/dev` (text-to-image puro, nessuna reference) — scelto al posto di `fal-ai/flux-pro/kontext` (già presente come default in `config/services.php` ma per image-editing con immagine di input, non adatto qui).
- `GenerateInstagramPostJob::handleEditorialBrainFlow()`: ramo `oggetto` usa `generateStandalone()` invece di `generate()`, `$reference` resta `null`, negative prompt rinforzato (`, person, human, face, body, portrait`). `reference_asset_id` diventato `$reference?->id` — prima era una chiamata di metodo su `null`, fatale per qualunque post "oggetto".

**Fix di coerenza visiva, separato ma applicato nella stessa sessione** (bug osservato in produzione, non parte del piano originale di Fase 2): un post reale con Fernando e Pippo (il cane) ha prodotto un'immagine con un cane vero — inventato dal modello, perché Pippo non ha nessuna reference salvata da nessuna parte, quindi non sarà lo stesso cane la prossima volta. Stesso meccanismo di fondo ha prodotto un maglione in un'immagine di piena estate. Aggiunti due bullet in `buildPrompt()`: (1) `prompt_immagine` deve rispettare la stagione indicata in `$oggi['stagione']`, anche se il profilo visivo del personaggio menziona capi non stagionali in generale; (2) `prompt_immagine` deve raffigurare **solo** il personaggio principale come persona (o nessuna persona per "oggetto") — altre persone o animali nominati in testo/idea restano presenze solo narrative, mai visive, finché non esiste un sistema di reference per le relazioni (idea per una fase futura, non costruita: campo di descrizione visiva per relazione + asset di riferimento dedicato, comunque mai insieme al personaggio principale nella stessa immagine — limite del modello `fal-ai/ideogram/character`, che secondo la documentazione ufficiale usa solo la prima reference di `reference_image_urls`, ignorando le altre).

**Verificato**:
- Pannello Filament non rotto dopo tutte le modifiche (redirect 302 normale su `/aiadmin/characters`, nessun errore nuovo in `storage/logs/laravel.log`).
- Parte deterministica testata in tinker senza chiamate esterne: cadenza diario (mai fatto un diario → sempre disponibile; diario recente con meno di `diario_ogni_n_post` post successivi → non disponibile), `formatoEnum()`/`describeFormatOptions()` con e senza diario disponibile, prompt renderizzato via `lastPrompt()` con e senza `$diarioDisponibile` (bullet diario presente solo quando atteso, nessun esempio hardcoded residuo).
- Ciclo editoriale reale (`character:run-editorial-cycle sofia`, una sola chiamata OpenAI, nessuna generazione immagine — quel comando non arriva a `GenerateInstagramPostJob`) usato per verificare **anche** il fix di coerenza visiva: `prompt_immagine` risultante coerente con l'estate ("short-sleeve t-shirt during a summer afternoon") e dichiara esplicitamente "no other people, no animals" pur avendo in storyline un riferimento a un cliente. Post #24 creato in draft come effetto collaterale di questo test (formato `story`, nessuna immagine generata).
- **Aggiornamento stesso giorno — generazione immagine reale per "oggetto" verificata end-to-end**: dispatchato `GenerateInstagramPostJob` con `useEditorialBrain: true` e un'istruzione che spinge esplicitamente verso il formato (il cervello editoriale non lo sceglie spesso di sua iniziativa nei cicli liberi). Risultato: `formato: oggetto` scelto davvero, `prompt_immagine` senza persone ("no people in frame", occhiali/quaderno/laptop sulla scrivania), post creato con `narrative_format: oggetto`, `media_type: image`, immagine reale generata e salvata, `reference_asset_id` nullo in `generations.output` (confermato: nessuna reference selezionata per questo ramo, come atteso). **Bug reale trovato nello stesso test**: `fal-ai/flux/dev` risponde di default con `output_format: jpeg` (confermato sulla documentazione ufficiale fal.ai), ma `GenerateInstagramPostJob` salva sempre con estensione `.png` — il file su disco era quindi un JPEG mascherato da `.png`. Non rompeva la pubblicazione (`Storage::response()` rileva il Content-Type dal contenuto reale, non dall'estensione), ma restava fuorviante per qualunque lettura/debug futuro. Corretto aggiungendo `output_format: 'png'` esplicito al payload di `generateStandalone()`, riverificato con una chiamata diretta al metodo (`file` conferma "PNG image data").

**Commit** (quattro, non tre come pianificato inizialmente — il fix di coerenza visiva è arrivato a metà sessione ed è stato isolato nel proprio commit): schema dati (migrations + `CharacterEditorialSettings`/`Post`), cervello editoriale (diario/oggetto in `EditorialBrainService`/`EditorialCycleService`/`EditorialContextBuilder`), fix coerenza visiva (stagione + niente persone/animali secondari), pipeline immagine (`FalImageService::generateStandalone()` + `GenerateInstagramPostJob`). Le correzioni ai tre bug bloccanti trovati nel motore di memoria (punti 1-3 sopra) sono state applicate ma **non committate** in questa sessione — restano nel working tree, non più rotte, in attesa che quel lavoro (parallelo, non parte di questo piano) venga chiuso e committato a parte.

### 11.17 Rimozione `reel` dall'enum del cervello editoriale + audit `story`/`carousel` (22 luglio 2026)

**Contesto**: l'analisi `character-engine-nuovi-formati-contenuto.md` (21 luglio) segnalava un possibile gap silenzioso: `formato: reel` è un valore valido nello schema di `EditorialBrainService` fin dalla sezione 11.7, ma nel progetto non risultava mai comparire un servizio di generazione video.

**Verificato** (Claude Code locale, codice reale): confermato, e peggio del sospetto iniziale — non solo non viene generato alcun video (`GenerateInstagramPostJob::handleEditorialBrainFlow()` cade nell'`else` generico per `reel`, stessa immagine statica via `FalImageService::generate()` usata per `post`), ma in pubblicazione `InstagramGraphService` non ha nessun `createReelContainer`/gestione di `REELS`: il post con `media_type: 'reel'` non matcha nessun caso speciale e finisce pubblicato silenziosamente come immagine singola nel feed, senza errori né log.

**Fix**: `reel` rimosso da `formatoEnum()`/`describeFormatOptions()` in `EditorialBrainService.php` — il cervello editoriale non può più deciderlo. Nessuna migration necessaria (`posts.media_type`/`posts.narrative_format` sono colonne string libere, nessun vincolo enum/check a livello DB). Aggiunti solo commenti esplicativi in `EditorialCycleService`/`GenerateInstagramPostJob` (il ramo generico che tratterebbe `reel` come immagine resta nel codice ma è ora irraggiungibile lato cervello). Verificato via reflection/tinker che `reel` non compare più né nello schema né nel testo del prompt generato.

**Audit esteso** (stessa sessione, per escludere lo stesso tipo di gap altrove): `story` e `carousel` controllati end-to-end, entrambi corretti, nessun'azione necessaria. `story`: nessun ramo dedicato in generazione (immagine statica — corretto, perché una Instagram Story *è* legittimamente una semplice immagine), ma pubblicazione ha un caso dedicato (`buildStoryContainer` → `InstagramGraphService::createStoryContainer`, `media_type: STORIES` + `image_url`, contratto Instagram rispettato). `carousel`: wired correttamente end-to-end (coerente con 11.15), preso anzi come modello di riferimento per lo stile del fix di `reel` (escludere dall'enum finché la pipeline non è pronta).

**Commit**: `00f8bd4`. Push non fatto (nessun remote configurato sul repo).

### 11.18 Wizard pubblico di creazione personaggio, conversione bozza→Character, registrazione/login minimi, gate crediti (22 luglio 2026)

**Obiettivo**: piano approvato direttamente in sessione Claude Code locale (non in questo documento) per un flusso pubblico end-to-end: wizard di creazione → bozza persistita → registrazione → conversione in Character reale.

**Implementazione**:
- **Wizard pubblico** (`/crea-personaggio`) — componente Livewire `CharacterCreationWizard`, 7 step più intro, CSS del prototipo (realizzato in una sessione precedente) portato 1:1 in un layout dedicato. Micro-interazioni (tile/chip/swatch/slider) gestite in Alpine senza roundtrip per singolo click, sincronizzate con Livewire solo al passo "Avanti" (`$wire.nextStep(...)`). I 3 screen mancanti dal prototipo originale (Identità, Personalità, Come comunica) e i componenti mancanti (input testo, slider) sono stati costruiti nello stesso linguaggio visivo.
- **Persistenza & conversione** — tabella `character_drafts`, estesa oltre lo spec originale per coprire davvero l'intero Nucleo (temperamento, valori, vita quotidiana, ecc. — gap segnalato durante la pianificazione). `ConvertCharacterDraftToCharacter` genera, alla registrazione, `Character` + `CharacterEditorialSettings` + `CharacterVisualProfile` + le 5 sezioni bible in prosa, nello stesso stile già usato per Sofia.
- **Registrazione minima custom** (scelta di prodotto, nessun pacchetto esterno) — `RegisterController`/`LoginController` scritti a mano, creano `Tenant`+`User`. Login minimo aggiunto seduta stante, non previsto nel piano originale, ma necessario perché senza il middleware `auth` avrebbe fallito con `Route [login] not defined`.
- **Pannello post-registrazione** con gate crediti reale — primo utilizzo effettivo di `Tenant::creditBalance()` in tutto il codebase (mai chiamato prima, nemmeno nel pannello admin). Comando schedulato per la pulizia delle bozze scadute.
- **Modifica personaggi** — dopo login/registrazione, il pannello ha un menu laterale da cui si può riaprire e modificare il questionario già compilato in fase di creazione, oltre ad ampliarlo con una nuova sezione "approfondimenti" non presente nel wizard iniziale.

**Punti aperti, non ancora verificati**:
- `Tenant::creditBalance()` calcola qualcosa di corretto ora che viene davvero chiamato in un percorso utente reale, o resta da testare con dati reali?
- Il login minimo aggiunto ad-hoc copre solo il necessario per non rompere il middleware, o gestisce anche i casi normali (logout, "ricordami", reset password)? Da chiarire prima di considerarlo pronto per utenti reali.

**Commit**: `4b23a43`. Push non fatto (nessun remote configurato).

### 11.19 Incoerenza capelli di Sofia + generalizzazione ai personaggi del wizard (23-28 luglio 2026)

**Segnalazione**: la lunghezza dei capelli di Sofia cambiava in modo incoerente tra un post e l'altro, senza alcun evento narrativo a giustificarlo. Indagine completa e cronologia dei fix in `character-engine-coerenza-visiva-relazioni.md` (sez. 5-6); qui solo il riepilogo per la cronologia del progetto.

**Causa**: `ReferenceImageSelector::pick()` sceglieva a caso, a ogni generazione, tra 5 foto di reference già strutturalmente incoerenti fra loro (2 con capelli raccolti a chignon, 3 con capelli sciolti lunghi); il testo `hair_description` di Sofia era vuoto, con un dettaglio contraddittorio ("lunghezza spalle") annegato in `face_description` proveniente dall'import legacy — confermato che il modello di generazione segue il testo contraddittorio più della reference quando i due sono in conflitto.

**Fix applicati**:
- Pin di `pick()` all'asset `is_default=true` (id=9, chignon) + riscrittura di `hair_description`/pulizia di `face_description`. **Pushato su `origin/master`.** Effetto collaterale accettato come stopgap provvisorio: Sofia oggi ha un solo aspetto fisso, nessuna variazione di posa — non ancora il sistema "casuale ma con lunghezza coerente" richiesto, quel pezzo specifico per Sofia resta da fare (vedi sez. 6 del documento dedicato).
- **Generalizzazione ai personaggi del wizard** (commit `6b7c242`, `1412840`): stesso tipo di gap trovato anche nella pipeline di creazione nuovi personaggi — il questionario mescolava lunghezza e texture dei capelli in un unico campo (omissione della lunghezza se l'utente sceglieva una texture), e nessun vincolo di coerenza esisteva sul caricamento manuale di foto reference in Filament. Corretto con: nuovo campo `hair_length` dedicato e obbligatorio nel wizard (migration con backfill automatico dei dati esistenti — Paolo, unico altro personaggio nel sistema), e un avviso non bloccante in Filament quando una foto caricata dichiara una lunghezza diversa da quella canonica del personaggio. Suite di test aggiornata, 52/52 verdi.

**Chiuso il 28 luglio 2026**: selezione casuale ripristinata per Sofia (`is_default` rimosso da tutti e 5 gli asset), `hair_description` riscritto con ancoraggio preciso (capelli sciolti fino alla vita, mai oltre) verificato presente in ogni prompt a prescindere da quale reference viene estratta. `pick()` riverificato: distribuzione su tutti e 5 i tipi in 20 chiamate ripetute. Nessun commit necessario (solo dati DB). Caso chiuso, dettagli completi in `character-engine-coerenza-visiva-relazioni.md` sez. 5.

### 11.20 Formato "conversazione" (mockup chat senza foto) + scoperta di un fix non loggato (23-29 luglio 2026)

**Contesto**: durante l'implementazione del formato "conversazione" (opzione A del documento nuovi formati, sez. 3), Claude Code ha scoperto che il formato "screenshot" (overlay di bolle di chat sopra una foto reale del personaggio) esisteva già in produzione, completo e funzionante — commit `d8eba06` (23 luglio 2026), mai loggato in `character-engine-nuovi-formati-contenuto.md` né qui. Stesso commit ha introdotto anche l'overlay quote-card sul post a immagine singola. Corretto ora in entrambi i documenti.

**Decisione presa**: il nuovo formato "conversazione" non sostituisce "screenshot" (che userebbe uno schema dati incompatibile, `messaggi_chat` come array di stringhe contro `messaggi_conversazione` come oggetti `{mittente, testo}`) — aggiunto come formato distinto per non rischiare di rompere codice già in produzione. I due restano concettualmente diversi: "screenshot" mostra il personaggio in scena con messaggi sovrapposti, "conversazione" è un mockup puro senza nessuna foto.

**Implementazione — commit `ed20441`** (comprende anche gli altri due fix della stessa sessione, 11.19-adiacenti: separazione lunghezza/texture nel wizard e avviso Filament, vedi 11.19): nuovo valore enum `conversazione` in `EditorialBrainService`, campo `messaggi_conversazione` con guardia di validazione (≥2 messaggi, `prompt_immagine` deve restare `null` per questo formato), nuovo metodo `ImageTextOverlayService::renderConversationMockup()` che disegna un canvas vuoto (header con nome contatto e avatar a iniziale, bolle alternate con orari sintetici, nessuna libreria esterna — arrotondamento rettangoli fatto a mano perché GD non lo offre nativamente). `GenerateInstagramPostJob` salta del tutto `FalImageService` per questo formato: nessuna reference, nessun costo di generazione immagine — il contenuto più economico da produrre tra tutti quelli esistenti.

**Push**: `origin/master` allineato a `ed20441`.

### 11.21 Formato "tema_caldo" — reazioni a controversie Reddit pertinenti alla nicchia del personaggio (29 luglio 2026)

**Obiettivo**: leva di crescita alternativa alle sponsorizzazioni automatiche — un personaggio reagisce a temi che stanno facendo discutere sui social (non notizie giornalistiche: il caso guida discusso in pianificazione è un video virale di una dottoressa sulle creme solari, migliaia di post di reazione in 36 ore). Analisi completa in `character-engine-temi-attualita.md`. Collega finalmente il campo `used_news` dello schema del cervello editoriale, lasciato lì dalla sez. 11.8 e mai usato.

**Fonte dati**: Reddit (non NewsData.io, valutato e scartato in analisi — copre articoli di testate, non virale social). Piano gratuito, 100 richieste/minuto.

**Implementazione — commit `e290b7c`**: `RedditService` (OAuth app-only, sola lettura); `ConvertCharacterDraftToCharacter::suggestHotTopicSubreddits()` (3-6 subreddit via LLM alla creazione, editabili in Filament — checkpoint umano, stesso principio già scelto per le reference delle relazioni); comando schedulato `character:check-hot-topics` ogni 4 ore con filtro a due stadi (soglia grezza → giudizio LLM → guardia lato codice sulle categorie a rischio, non aggirabile dal giudizio del modello); nuovo formato `tema_caldo` in `EditorialBrainService`, disponibile solo con un `HotTopic` pending; generazione immagine via `generateStandalone()` senza reference, stesso trattamento di `oggetto`. Verificato con due chiamate LLM reali (suggerimento subreddit, giudizio su tema plausibile approvato e tema di tragedia correttamente scartato) e una generazione immagine reale. Suite 52/52 verde.

**Bloccante scoperto lo stesso giorno — poi superato con un pivot, vedi 11.22**: `REDDIT_CLIENT_ID`/`REDDIT_CLIENT_SECRET` da impostare in `.env` dopo aver registrato l'app su reddit.com/prefs/apps (richiede un umano, non automatizzabile) — ma Reddit ha eliminato la creazione self-service delle API key a novembre 2025 (approvazione manuale obbligatoria, tempi/criteri non pubblici), e nella stessa sessione è emerso che anche l'endpoint pubblico anonimo di Reddit è stato bloccato nel 2026. Nessuna delle due vie Reddit è quindi praticabile oggi senza approvazione manuale.

### 11.22 Pivot a YouTube Data API v3 per "tema_caldo" — fatto e verificato con dati reali (29 luglio 2026)

**Motivo**: sia l'OAuth self-service sia l'endpoint pubblico anonimo di Reddit sono risultati bloccati (vedi bloccante di 11.21). YouTube Data API v3 è realmente self-service e istantanea (chiave da Google Cloud Console, nessuna approvazione, 10.000 unità gratuite/giorno). Analisi completa e verifiche dettagliate in `character-engine-temi-attualita.md` sez. 8; qui solo il riepilogo per la cronologia del progetto.

**Reddit non cancellato**: `RedditService` (commit `e290b7c`) resta nel codice, intatto e dormiente — pronto come seconda fonte se/quando arriverà un'approvazione OAuth. `hot_topic_subreddits` resta anch'essa in DB/Filament, etichettata "(dormiente)".

**Design guidato dal vincolo di quota**: `search.list` costerebbe 100 unità/chiamata (~100 ricerche/giorno in totale, insufficiente scalando con più personaggi ogni 4 ore); scelto invece `videos.list(chart=mostPopular)` a 1 unità/chiamata per recuperare un **pool condiviso** di video di tendenza (poche chiamate per ciclo, indipendente dal numero di personaggi) — il match contro la nicchia di ciascun personaggio avviene poi in memoria, gratis. ID categoria YouTube verificati via ricerca web prima di scrivere il codice, per non rischiare valori sbagliati che fallirebbero silenziosamente.

**Implementazione — commit `2d4cf1a`**: `YoutubeTrendingService` (nuovo, `videos.list` su 5 categorie con `regionCode=IT`); `character_editorial_settings.hot_topic_keywords` (nuova colonna, affianca `hot_topic_subreddits` senza sostituirla); `ConvertCharacterDraftToCharacter::suggestHotTopicKeywords()` (stesso pattern LLM+checkpoint umano già usato per le subreddit, entrambi i suggerimenti girano alla creazione così Reddit non serve backfill in futuro); `character:check-hot-topics` riscritto per confrontare le parole chiave di ogni personaggio contro il pool condiviso invece di interrogare una fonte esterna per personaggio, stesso filtro a due stadi e stessa guardia sulle categorie a rischio di prima; schema del giudizio LLM irrobustito (`indice_scelto` numerico invece di match testuale per label).

**Verificato**: fallimento pulito senza `YOUTUBE_API_KEY`; suite 52/52 verde, `RedditService` confermato non toccato; tre chiamate LLM reali (suggerimento parole chiave, giudizio tema tecnico approvato, giudizio cronaca nera scartato). **Dopo l'aggiunta della chiave reale in produzione**: `YoutubeTrendingService` verificato con chiamata vera (70 video reali recuperati su 5 categorie); comando eseguito a vuoto senza personaggi con parole chiave configurate → zero side-effect; giro end-to-end forzando temporaneamente parole chiave larghe su Paolo → filtro LLM ha mostrato variabilità sensata su un caso di confine, poi dati di test rimossi e Paolo ripristinato alle sue 6 parole chiave reali (backfill legittimo, era stato creato prima che la colonna esistesse).

**Stato attuale**: pipeline attiva, chiave impostata e riconosciuta. Resta solo da popolare `hot_topic_keywords` sugli altri personaggi da includere (Sofia non le ha ancora).

### 11.23 Eliminazione personaggi/bozze dal menu pubblico — soft delete, cestino, eliminazione fisica (29 luglio 2026)

**Obiettivo**: dal menu "Personaggi" pubblico (il pannello utente finale del wizard, non Filament — sez. 11.18), poter eliminare un personaggio con modale di conferma, eliminazione **logica** (non fisica) verso un cestino, da cui ripristinare o eliminare **definitivamente** con una seconda modale di avviso più severa. Richiesta indipendente dal lavoro di design sulle comparse (`character-engine-comparse-mondo-narrativo.md`), ma con un punto di intersezione lasciato esplicitamente aperto — vedi sotto.

**Implementazione — commit `47689b6`**:
- `Character`: `SoftDeletes` già presente nello schema ma finora inutilizzato da qualunque flusso — ora attivato da `CharacterList::deleteCharacter()` (`$character->delete()`, logica): il personaggio sparisce automaticamente da ogni query dell'app, incluse le pipeline schedulate, grazie allo scope globale di Eloquent — nessun altro file da toccare per questo effetto.
- Nuova pagina `/personaggi/cestino` (Livewire `CharacterTrash`) — elenco dei personaggi con `deleted_at` valorizzato, azioni "Ripristina" e "Elimina per sempre" (`forceDelete()`, fisica e irreversibile — le tabelle collegate con foreign key a cascata ripuliscono bible/asset/post/generazioni collegate).
- Componente Blade riusabile `<x-confirm-modal>` (Alpine puro, nessun round-trip Livewire per aprire/chiudere).
- Autorizzazione per tenant su ogni azione (stesso pattern di `CharacterDetail`). Rotta `/personaggi/cestino` registrata **prima** di `/personaggi/{character}` nel routing — altrimenti "cestino" verrebbe risolto come binding di un `{character}` (404).

**Verificato**: 10 test Feature nuovi (eliminazione logica sparisce dal menu, isolamento tra tenant su eliminazione/ripristino/eliminazione definitiva, stato vuoto, autenticazione richiesta) — suite verde, nessuna regressione. Rendering reale delle due pagine controllato via richiesta HTTP autenticata. **Non verificato**: aspetto/interazione reale del modale in browser (solo compilazione Blade/comportamento server-side).

**Seguito, stessa sessione, due correzioni via feedback diretto**:
1. **Eliminazione bozze in corso** aggiunta (`CharacterList::deleteDraft()`) — **fisica, non logica**: `character_drafts` non ha `SoftDeletes` (stesso trattamento già riservato da `character-drafts:prune-expired`, che le elimina fisicamente alla scadenza) — non portano identità/bible/asset di un personaggio vero, nessun cestino necessario. Isolamento per tenant verificato con test dedicati.
2. **Icona cestino illeggibile** — l'emoji 🗑 sostituita con un'etichetta testuale ("Elimina") sia per personaggi sia per bozze; modale generalizzato per due messaggi distinti (personaggio → verso il cestino, recuperabile; bozza → eliminazione definitiva, non recuperabile).

**Punto lasciato esplicitamente aperto** (segnalato anche in `character-engine-comparse-mondo-narrativo.md` sez. 1.1): questa funzione è stata costruita **prima** che esistesse `character_relationships.related_character_id` (piano comparse, non ancora implementato) — non gestisce quindi ancora cosa succede a una relazione collegata quando il `Character` referenziato viene eliminato per sempre. Resta da decidere in Fase 1 del piano comparse: cancellazione a cascata della relazione, o degrado a `related_character_id = null` (relazione che torna solo testuale, opzione consigliata in quel documento per non perdere dati narrativi).

**Non ancora affrontato**: nessun blocco/avviso esplicito se si elimina un personaggio "principale" con `social_account` collegato e pubblicazione attiva — l'eliminazione procede oggi senza controlli su questo, lacuna di prodotto ancora da decidere.

### 11.24 Il mondo intorno al personaggio — Fase 0 (fix ripetitività) e Fase 1 (collegamento comparse) fatte (29 luglio 2026)

Piano completo in `character-engine-comparse-mondo-narrativo.md` (11.24 ne riprende solo l'avanzamento). Lavoro a fasi con commit isolati, come da piano.

**Fase 0 — indagine + fix ripetitività**:
- Causa reale di Fernando "sempre a costruire": non una riga hardcoded ma sovra-rappresentazione su più sezioni della bible reale di Sofia (aneddoto ripetuto 4-5 volte in punti diversi, unico gancio comico/narrativo scritto per lui). Causa reale della monotonia di Sofia sui bug: solo due storyline esistenti in tutto il DB (entrambe lavoro/Fernando) + `TimelineEntry.topic` mai popolato dal flusso attivo (`GenerateInstagramPostJob::handleEditorialBrainFlow()`, solo dal legacy) — l'anti-ripetizione esisteva solo come auto-giudizio del modello sul testo libero, nessun tracciamento strutturato di categoria.
- **Fix di codice — commit `3f8a19f`, verificato zero riferimenti a Sofia/Fernando nel diff (principio multi-tenant rispettato)**: `topic` ora popolato anche dal flusso attivo; nuovo campo `topic` nello schema del cervello editoriale; `EditorialContextBuilder::buildTopicFrequency()` — conteggio deterministico degli ultimi 8 topic, nuova sezione "VARIETÀ DI ARGOMENTI" nel prompt se una categoria supera metà delle pubblicazioni recenti.
- **Dati specifici di Sofia (nessun commit, solo DB)**: secondo aggancio comico per Fernando ("le grigliate epiche"); due nuove storyline seminate per Sofia ("Le domande impossibili di Marta", "Pippo e la gallina di gomma").
- Suite 64/64 verde.

**Fase 1 — collegamento comparse**:
- **Commit `9a7df76`**: `character_relationships.related_character_id` (nullable, `nullOnDelete`) + relazione `relatedCharacter()`. **Nessuna colonna di ruolo** (principio deciso in revisione, vedi `character-engine-comparse-mondo-narrativo.md` sez. 1) — un personaggio "secondario" resta un `Character` normale in `draft`.
- Verificato con un caso reale: Fernando creato come vero `Character` (`status: draft`, nessun `social_account`), collegato alla relazione testuale già esistente di Sofia — confermato completamente dormiente su tutta la pipeline automatica (anche invocato esplicitamente per id, `character:live-tick` lo rifiuta), confermando che nessuna nuova colonna/filtro di stato era necessaria.

**Fase 2 — wizard biforcato persona/animale — fatta, commit `26b1522` (29 luglio 2026)**:
- Nuovo step "mondo" nel wizard pubblico, sempre presente (non gated su edit-mode/auth), biforcato per tipo fin dalla prima versione (persona: tipo di relazione da 11 valori + descrizione libera; animale: specie/razza/aspetto).
- `character_drafts.world_characters` (json) + `ConvertCharacterDraftToCharacter::syncWorldCharacters()`: crea/aggiorna un `Character` reale in `draft` per voce, con update-in-place su riconversione (mai duplicato).
- Verificato leggendo il codice reale che `character_visual_profile` degrada pulito via `array_filter()` per i campi non popolati — nessun profilo dedicato per animali necessario.
- **Decisione presa durante l'implementazione**: per le persone con relazione valida, scritta anche una riga `CharacterKinship` (compare nella tab "Parentele" esistente) oltre alla `CharacterRelationship`. Il campo "foto opzionale" per queste voci è stato deliberatamente rimandato alla Fase 5 (consenso legale) invece di essere incluso ora.
- Suite 64 test/296 assertion verde, nessuna stringa Sofia/Fernando nel diff (verificato).

**Resta da fare**: generazione immagine multi-soggetto (`nano-banana-pro`, Fase 3), asset caricati dall'utente + consenso legale (Fase 5, ora include anche la foto delle voci del mondo del personaggio).

## 12. Prossimi passi possibili

1. ~~Riconciliare il cervello editoriale con la pipeline di generazione/pubblicazione già esistente~~ — **fatto il 17 luglio 2026, vedi 11.14**, incluso l'inoltro di `ScheduledPost.instructions` (11.14) e la riabilitazione di `carousel` (11.15), entrambi chiusi lo stesso giorno. Nessun punto aperto rimasto su questo fronte.
2. **Livello commerciale/sponsorizzazioni** — non ancora costruito, discusso il 16 luglio (vedi nota in 11.12): un brief commerciale per-personaggio (sponsor, prodotto, messaggio chiave, eventuale link di affiliazione, finestra di campagna, disclosure legale tipo "in collaborazione con") che alimenti il cervello editoriale con la stessa logica di pressione già usata per le storyline, invece di essere un caso speciale gestito a mano. `character_editorial_settings` (11.12) è il punto naturale dove agganciarlo.
3. **Logica crediti** — job/servizio che scala i crediti da `credit_ledger` prima di ogni generazione (prepagato, non a consuntivo).
4. ~~Pubblicazione Instagram~~ — disegno tecnico pronto (sezione 9, ma vedi 11.11: una parte è già implementata nel codice, il documento non era aggiornato). **Prima pubblicazione reale riuscita il 16 luglio** (11.13, Post#13, pubblicato come immagine singola). Il gap `carousel` del cervello editoriale (testo quote-card vs paragrafi narrativi lunghi) è **stato risolto il 17 luglio, vedi 11.15** — il cervello editoriale ora scrive `carousel_slides` in stile quote-card, riusando `ImageTextOverlayService` esistente. Resta solo, se in futuro lo stile quote-card si rivelasse limitante: valutare un vero motore multi-immagine (opzione scartata per questo fix, non per principio).
5. ~~App Review Meta~~ — **richiesta sottomessa il 28 luglio 2026** (`instagram_business_content_publish` + dipendenza `instagram_business_basic`, prerequisiti — business verification, privacy policy — completati prima della sottomissione). In attesa dell'esito di Meta, tempi non garantiti (giorni/settimane), indipendenti dallo sviluppo.
6. **Risorse admin minori non ancora fatte**: `CreditPackage`, gestione utenti multi-tenant, viste `generations`/`posts` (più utili una volta che la logica di generazione esiste davvero).
7. **Eventuale import degli altri personaggi abbozzati** (fernando, marta, minnie, pippo, sara, sushi) e delle regole globali (`visual_bible/global/`), se si decide di usarli.
8. **Decidere se stories e reel entrano nella prima versione** o restano fase 2 (parametri diversi: `media_type=STORIES`/`REELS`, gestione video/cover, eventuale resumable upload per i video più pesanti).
9. **Tarare i parametri del motore di vita osservando Sofia per qualche settimana** (`config/life_engine.php`): decay_rate, finestra pressione storyline, soglia abbandono, probabilità base evento e relativo tetto (vedi nota aperta in 11.4).
10. **Popolare `hot_topic_keywords` sugli altri personaggi** (11.22) — pipeline `tema_caldo`/YouTube attiva e verificata, ma Sofia e altri personaggi esistenti (creati prima della colonna) non hanno ancora parole chiave configurate: senza, `character:check-hot-topics` non troverà mai nulla per loro.
11. **Il mondo intorno al personaggio: persone/animali collegati, relazioni visive, asset caricati dall'utente, riduzione ripetitività** — analisi completa in `character-engine-comparse-mondo-narrativo.md` (29 luglio 2026), non ancora implementato. Nasce dal caso reale di Sofia (marito Fernando, cane, pesce rosso, 2 figlie) oggi solo parzialmente presente nei contenuti pubblicati, più due bug di monotonia osservati in produzione (Fernando sempre "a costruire qualcosa", Sofia sempre sui bug informatici). Include: collegare `character_relationships` a `Character` veri e propri via `related_character_id` (**nessuna etichetta di ruolo permanente** — un personaggio "secondario" resta un `Character` normale in `draft`/senza `social_account`, libero di essere attivato come profilo pubblicante a sé stante in futuro senza migrazione dati, correzione decisa in revisione lo stesso giorno), generazione immagine multi-soggetto (risolve il limite di `fal-ai/ideogram/character` segnalato in 11.16 — verificato che `fal-ai/nano-banana-pro` supporta fino a 5 persone coerenti nella stessa immagine), estensione del wizard per nominare queste persone/animali, asset caricati dall'utente senza consumo crediti con relativo flusso di consenso legale (da far rivedere da un legale prima del lancio). Ordine di lavoro consigliato nel documento dedicato, sez. 6.
