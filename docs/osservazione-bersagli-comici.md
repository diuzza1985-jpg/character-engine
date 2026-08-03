# Osservazione bersagli comici — Sofia

Log settimanale del controllo automatico sui bersagli comici usati nei post Instagram di Sofia, per verificare se il fix del 24/07/2026 (caption piena nel prompt del cervello editoriale + istruzione a non ripetere bersagli) sta funzionando.

## Ultimo controllo: 2026-08-03

⚠ CONTROLLO NON ESEGUITO — impossibile chiamare l'API Instagram Graph. La chiamata `GET https://graph.instagram.com/v21.0/{IG_USER_ID}/media` ha fallito con `CONNECT tunnel failed, response 403` a livello di proxy di rete dell'ambiente di esecuzione (host `graph.instagram.com` non incluso nella policy di egress consentita per questa sessione). Non è stato analizzato nessun post nuovo e non è stato possibile confrontare la finestra delle ultime due settimane. Nessun bersaglio comico registrato in questa esecuzione.

Azione consigliata: verificare/abilitare `graph.instagram.com` nella policy di rete dell'ambiente schedulato usato per questo controllo, poi rilanciare manualmente il task.

## Storico controlli

### 2026-08-03
- Nessun post analizzato — errore di rete (vedi sopra), API Instagram irraggiungibile da questo ambiente.
