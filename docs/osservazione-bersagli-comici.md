# Osservazione bersagli comici — Sofia

Log settimanale del controllo automatico sui bersagli comici usati nei post Instagram di Sofia, per verificare se il fix del 24/07/2026 (caption piena nel prompt del cervello editoriale + istruzione a non ripetere bersagli) sta funzionando.

## Ultimo controllo: 2026-08-17

⚠ CONTROLLO NON ESEGUITO PER LA TERZA SETTIMANA CONSECUTIVA — impossibile chiamare l'API Instagram Graph. La chiamata `GET https://graph.instagram.com/v21.0/{IG_USER_ID}/media` ha fallito di nuovo con `CONNECT tunnel failed, response 403` (proxy status: `connect_rejected`, "gateway answered 403 to CONNECT (policy denial or upstream failure)", host `graph.instagram.com:443`). L'host non è incluso nella policy di egress consentita per questo ambiente schedulato. Non è stato analizzato nessun post nuovo, per la terza settimana di fila.

Azione consigliata: invariata rispetto ai controlli precedenti — questa non è una condizione transitoria da ritentare automaticamente. La policy di rete dell'ambiente schedulato deve essere aggiornata per includere `graph.instagram.com` prima che questo controllo possa produrre risultati utili. Nel frattempo il monitoraggio settimanale dei bersagli comici resta fermo e non copre le ultime tre settimane di post (dal 03/08 al 17/08/2026). Se il blocco persiste ancora la prossima settimana, andrebbe trattato come un problema strutturale dell'ambiente da risolvere manualmente, non più come guasto occasionale.

## Storico controlli

### 2026-08-17
- Nessun post analizzato — errore di rete (vedi sopra), API Instagram ancora irraggiungibile da questo ambiente (stesso errore delle due settimane precedenti).

### 2026-08-10
- Nessun post analizzato — errore di rete (vedi sopra), API Instagram ancora irraggiungibile da questo ambiente (stesso errore della settimana precedente).

### 2026-08-03
- Nessun post analizzato — errore di rete (vedi sopra), API Instagram irraggiungibile da questo ambiente.
