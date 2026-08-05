# Character Engine — Deploy su Netsons (hosting condiviso multisite, senza SSH)

*Guida scritta il 5 agosto 2026, motivata dal bisogno reale di un cron sempre attivo — l'ambiente di sviluppo locale (WSL2/Windows) si sospende quando il PC è spento, causando ritardi nell'automazione giornaliera (vedi sez. 11.26 di `character-engine-project.md`). Dominio di destinazione: `characterengine.cthosting.it` (sottodominio di terzo livello), piano Netsons multisite **senza accesso SSH** (verificato con l'utente prima di scrivere questa guida — cambia l'intero approccio rispetto a un deploy via git/composer sul server).*

## 0. Cosa serve davvero (verificato nel codice reale, non assunto)

Prima di scrivere la guida ho controllato cosa l'app usa realmente, per non chiedere più infrastruttura del necessario:

- **Nessun Redis richiesto**: sessioni/cache/coda sono già configurate su `database` (`SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`) — Redis è presente solo nello stack Sail locale, mai obbligatorio.
- **Nessun queue worker richiesto**: verificato che l'unico dispatch asincrono reale rimasto nel codice (`PublishInstagramPostJob::shareToStoryIfNeeded()`) è stato appena corretto in `dispatchSync()` — bug reale trovato proprio preparando questo deploy (jobs mai processati, 3 Story rimaste bloccate per giorni, vedi commit `c022543`). Tutto il resto del codebase (`GenerateInstagramPostJob`, `ProcessScheduledPostJob`, l'automazione giornaliera) già usa `dispatchSync`/`dispatchNow` di proposito.
- **Nessun `storage:link` richiesto**: nessun punto del codice usa il disco `public` di Laravel — tutti gli asset (foto personaggi, immagini generate) passano dalla route firmata `storage/{path}` sul disco `local` (verificato, sez. Fase 4 mondo narrativo).
- **Nessuna email richiesta**: nessun `Mail::`/`->notify()` nel codebase — `MAIL_MAILER` può restare su `log` senza rompere nulla, non serve un vero SMTP per ora.
- **Un solo database**: in locale esistono `laravel` + `testing` (per PHPUnit) — in produzione serve solo l'equivalente di `laravel`.
- **L'intera automazione si riduce a UN SOLO comando cron**: `php artisan schedule:run`, eseguito ogni minuto, gestisce da solo tutto ciò che è già dichiarato in `routes/console.php` (materializzazione slot, tick del motore di vita, pianificazione giornaliera con orario deciso dal cervello editoriale, pubblicazione automatica, ecc.) — non serve replicare ogni singolo comando come riga di cron separata.

## 1. Prerequisiti da verificare nel pannello Netsons prima di iniziare

- **Versione PHP disponibile**: il progetto richiede PHP `^8.3` (verificato in `composer.json`). Nel pannello cPanel, sezione "MultiPHP Manager" (o "Seleziona versione PHP"), verifica che sia disponibile almeno PHP 8.3 per il nuovo sottodominio — se disponibile una versione più recente (8.4/8.5) va bene comunque, purché ≥ 8.3.
- **Estensioni PHP necessarie** (Laravel le richiede tutte): `openssl`, `pdo_mysql`, `mbstring`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `curl`, `fileinfo`, `gd` (per la manipolazione immagini). Nel MultiPHP Manager/PHP Selector di cPanel, abilita quelle non già attive di default (di solito lo sono quasi tutte già).
- **MySQL disponibile**: sì per qualunque piano cPanel standard — verifica solo di avere margine sul numero di database/utenti MySQL consentiti dal piano (di solito ne basta uno).
- **Cron Jobs**: verifica che la sezione "Cron Jobs" sia presente nel pannello (lo è su tutti i piani Netsons standard, inclusi quelli senza SSH).

## 2. Preparare i file da caricare (build fatta in locale, non sul server)

Senza SSH sul server, `composer install` non può girare lì — va fatto **in locale** (in questo stesso ambiente WSL) e poi caricata anche la cartella `vendor/` risultante, cosa che normalmente non si fa (non è mai committata in git, resta un'eccezione solo per questo tipo di deploy).

```bash
# Nella cartella del progetto, con Docker Sail già attivo:
docker compose exec laravel.test composer install --no-dev --optimize-autoloader
docker compose exec laravel.test php artisan config:cache
docker compose exec laravel.test php artisan route:cache
docker compose exec laravel.test php artisan view:cache
```

Nota importante su `config:cache`/`route:cache`: una volta eseguiti, l'app legge la configurazione dalla cache compilata, non più da `.env` a runtime per ogni singola chiamata `env()` sparsa nel codice (in questo progetto `env()` è usato solo dentro `config/*.php`, mai direttamente nei Controller/Job — verificato, quindi è sicuro cachare). Se in futuro cambi una variabile in `.env` sul server, **devi rilanciare `config:cache`** (o cancellarla con `config:clear`) perché il cambiamento abbia effetto — punto a cui prestare attenzione visto che non avrai una shell per farlo al volo (vedi sez. 8 sotto per come lanciare comandi artisan senza SSH).

Poi prepara un pacchetto con tutto il necessario, **escludendo**: `.git/`, `node_modules/`, `storage/framework/testing`, `.env` (quello va creato a mano direttamente sul server, mai caricato/committato — vedi sez. 6), e i file Docker/Sail (`compose.yaml`, `vendor/laravel/sail/`, `.devcontainer/` se presente) che non servono su hosting condiviso.

## 3. Creare il sottodominio in cPanel

In cPanel → "Domini"/"Sottodomini": crea `characterengine` come sottodominio di `cthosting.it`, così da ottenere `characterengine.cthosting.it`.

**Punto critico Laravel su cPanel**: il "document root" del sottodominio deve puntare alla cartella `public/` del progetto, **non** alla radice del progetto. Se carichi il progetto in `~/characterengine/`, imposta il document root del sottodominio su `~/characterengine/public` — altrimenti chiunque visiti il sito vedrebbe la struttura interna dell'app (incluso, potenzialmente, un `.env` mal protetto) invece della sola interfaccia pubblica. Questo è il passo che più spesso viene sbagliato in un deploy Laravel su hosting condiviso: verificalo due volte prima di procedere.

## 4. Creare il database MySQL

In cPanel → "MySQL Databases": crea un nuovo database e un nuovo utente, assegna all'utente tutti i privilegi su quel database. Netsons (come la maggior parte dei cPanel condivisi) prefissa automaticamente nome database e utente con il tuo username account (es. `nomeaccount_characterengine`) — annota i nomi esatti generati, ti serviranno nel `.env`.

## 5. Caricare i file via FTP

Con un client FTP (FileZilla o simile) collegato alle credenziali FTP del tuo account Netsons, carica l'intero contenuto preparato al punto 2 dentro la cartella scelta al punto 3 (es. `~/characterengine/`, con `public/` che diventa il document root del sottodominio).

Imposta i permessi di scrittura su:
- `storage/` (e tutte le sottocartelle, ricorsivamente)
- `bootstrap/cache/`

Di solito `755` per le cartelle basta su cPanel (il processo PHP gira come il tuo utente, non serve `777`, che sarebbe comunque sconsigliato per motivi di sicurezza).

## 6. Configurare `.env` direttamente sul server

**Mai caricare il tuo `.env` locale via FTP così com'è** (contiene URL/credenziali di sviluppo, es. il tunnel ngrok attuale) — crealo da zero direttamente sul server, con il File Manager di cPanel (editor di testo integrato) o via FTP creando un nuovo file vuoto e incollando il contenuto.

Valori da impostare (solo le chiavi che cambiano rispetto al locale — copia i valori "segreti" dal tuo `.env` locale tramite un canale che preferisci, non li riporto qui):

```env
APP_NAME="Character Engine"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://characterengine.cthosting.it
APP_KEY=                      # genera con il comando alla sez. 7, non lasciarlo vuoto

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=nomeaccount_characterengine   # esatto nome creato in cPanel, sez. 4
DB_USERNAME=nomeaccount_...
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_DOMAIN=null
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

MAIL_MAILER=log

# Copia questi dal tuo .env locale (stessi valori, stesse chiavi di OpenAI/Fal/Instagram/YouTube):
OPENAI_API_KEY=
OPENAI_TEXT_MODEL=
FAL_API_KEY=
FAL_MODEL=
INSTAGRAM_APP_ID=
INSTAGRAM_APP_SECRET=
INSTAGRAM_REDIRECT_URI=https://characterengine.cthosting.it/instagram/callback
YOUTUBE_API_KEY=
NEWSDATA_API_KEY=
```

**Punto critico**: `INSTAGRAM_REDIRECT_URI` cambia dominio — va aggiornato **anche** nel pannello sviluppatori Meta (Facebook for Developers → la tua app → prodotto Instagram/Facebook Login → "URI di reindirizzamento OAuth validi"), altrimenti il collegamento dell'account Instagram fallirà con un errore di Meta anche se il `.env` è corretto. Questo è un passo che solo tu puoi fare (richiede il tuo accesso al pannello Meta).

## 7. Generare `APP_KEY`

Senza SSH non puoi lanciare `php artisan key:generate` direttamente sul server. Generalo **in locale** e incollalo nel `.env` del server:

```bash
docker compose exec laravel.test php artisan key:generate --show
```

Copia il valore stampato (inizia con `base64:`) dentro `APP_KEY=` nel `.env` del server.

## 8. Eseguire le migration senza SSH

Senza terminale, il modo standard e sicuro per lanciare comandi `artisan` una tantum (migration, ma anche futuri comandi di manutenzione) è tramite **Cron Jobs di cPanel**, usati come "esegui un comando una volta sola": crei la riga, aspetti che scatti al minuto giusto, poi la disabiliti o cancelli.

In cPanel → "Cron Jobs", aggiungi temporaneamente:

```
* * * * * /usr/local/bin/php /home/nomeaccount/characterengine/artisan migrate --force >> /home/nomeaccount/characterengine/storage/logs/migrate-once.log 2>&1
```

(il percorso esatto del binario PHP lo trovi in cPanel stesso, spesso mostrato come suggerimento nella sezione Cron Jobs — su Netsons è tipicamente sotto `/usr/local/bin/php` o `/opt/cpanel/ea-php83/root/usr/bin/php`, verificalo nel tuo pannello).

Aspetta un minuto, controlla `storage/logs/migrate-once.log` (via File Manager) per confermare che sia andato a buon fine, **poi cancella subito questa riga di cron** (altrimenti proverebbe a rilanciare la migration ogni minuto — innocuo perché Laravel salta le migration già eseguite, ma inutile e rumoroso nei log).

Stesso identico meccanismo per qualunque comando `artisan` che in futuro vorrai lanciare una tantum senza SSH (es. un comando di manutenzione, un seed, ecc.).

## 9. Il cron reale — questo è il motivo di tutto il deploy

In cPanel → "Cron Jobs", aggiungi (questa volta **permanente**, non da cancellare):

```
* * * * * /usr/local/bin/php /home/nomeaccount/characterengine/artisan schedule:run >> /dev/null 2>&1
```

Questa singola riga, eseguita dal vero cron di sistema Netsons ogni minuto, sostituisce del tutto il servizio `scheduler` di Docker usato in locale (sez. 11.26) — gira indipendentemente da qualunque cosa faccia il tuo PC, è esattamente il problema che questo deploy risolve. Verifica dopo qualche minuto che compaiano righe recenti in `storage/logs/laravel.log`.

## 10. SSL/HTTPS

Netsons offre AutoSSL (Let's Encrypt) via cPanel, di norma automatico entro poche ore dalla creazione del sottodominio (a volte serve un'attivazione manuale in cPanel → "SSL/TLS Status"). `APP_URL` nel `.env` deve restare `https://` — se il certificato non è ancora attivo, l'app potrebbe momentaneamente servire su HTTP semplice finché non lo è: non pubblicare/collegare Instagram finché HTTPS non è confermato attivo (l'OAuth di Meta richiede HTTPS).

## 11. Verifica finale

- [ ] `https://characterengine.cthosting.it` carica la pagina pubblica (non un errore 500, non la struttura di cartelle nuda — se vedi quest'ultima, il document root al punto 3 non è impostato su `public/`).
- [ ] `https://characterengine.cthosting.it/aiadmin` chiede il login del pannello admin.
- [ ] Login funziona, `/aiadmin/posts` mostra lo storico reale (se hai copiato lo stesso database — vedi nota sotto).
- [ ] Dopo qualche minuto, `storage/logs/laravel.log` mostra attività dello scheduler (nessun errore ripetuto).
- [ ] Ricollega l'account Instagram di Sofia dal nuovo dominio (il vecchio token resta legato al vecchio `INSTAGRAM_REDIRECT_URI`/dominio in sessione, va rifatto almeno una volta).

**Nota sul database**: questa guida presume un database di produzione vuoto (nuove migration, nessun dato). Se invece vuoi **portare i dati esistenti** (Sofia, Sara, tutti i personaggi/post già generati) dal tuo MySQL locale a quello Netsons, serve un passaggio ulteriore (export/import via phpMyAdmin di cPanel, che su Netsons è quasi sempre disponibile anche senza SSH) — dimmelo se è quello che vuoi fare e prepariamo quel passaggio a parte, con più attenzione ai dati sensibili (token Instagram inclusi) da non esporre nel processo.

## 12. Come aggiornare in futuro (redeploy)

Senza SSH, ogni aggiornamento richiede: rifare il build locale (punto 2), ricaricare via FTP solo i file cambiati (un client FTP come FileZilla mostra le differenze), e se ci sono nuove migration ripetere il trucco del cron temporaneo (punto 8). Non è comodo quanto un `git pull` da SSH, ma è l'unica via compatibile con questo piano — se in futuro valuti un upgrade a un piano con SSH, il resto di questa guida resta valido, cambia solo come si eseguono i comandi.
