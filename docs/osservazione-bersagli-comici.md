# Osservazione bersagli comici — Sofia

Log settimanale del controllo automatico sui bersagli comici usati nei post Instagram di Sofia, per verificare se il fix del 24/07/2026 (caption piena nel prompt del cervello editoriale + istruzione a non ripetere bersagli) sta funzionando.

## Ultimo controllo: 2026-08-31

⚠ CONTROLLO NON ESEGUITO PER LA QUINTA SETTIMANA CONSECUTIVA — impossibile chiamare l'API Instagram Graph. La chiamata `GET https://graph.instagram.com/v21.0/{IG_USER_ID}/media` ha fallito di nuovo con `CONNECT tunnel failed, response 403` (proxy status: `connect_rejected`, "gateway answered 403 to CONNECT (policy denial or upstream failure)", host `graph.instagram.com:443`). L'host continua a non essere incluso nella policy di egress consentita per questo ambiente schedulato. Non è stato analizzato nessun post nuovo, per la quinta settimana di fila.

Azione consigliata: il blocco è confermato strutturale — cinque settimane consecutive con lo stesso identico errore di policy escludono un guasto occasionale. Continuare a ritentare automaticamente ogni settimana non produrrà risultati finché la policy di rete dell'ambiente schedulato non viene aggiornata manualmente per includere `graph.instagram.com`. Il monitoraggio dei bersagli comici resta completamente fermo dal 24/07/2026 (data del fix) a oggi: non copre nessuno dei post pubblicati da allora, quindi non è ancora possibile dire se il fix del 24/07 stia funzionando o meno. Si raccomanda di intervenire sulla configurazione di rete dell'ambiente prima della prossima esecuzione, altrimenti il task continuerà a non produrre valore.

## Storico controlli

### 2026-08-31
- Nessun post analizzato — errore di rete (vedi sopra), API Instagram ancora irraggiungibile da questo ambiente (stesso errore delle quattro settimane precedenti).

### 2026-08-24
- Nessun post analizzato — errore di rete (vedi sopra), API Instagram ancora irraggiungibile da questo ambiente (stesso errore delle tre settimane precedenti).

### 2026-08-17
- Nessun post analizzato — errore di rete (vedi sopra), API Instagram ancora irraggiungibile da questo ambiente (stesso errore delle due settimane precedenti).

### 2026-08-10
- Nessun post analizzato — errore di rete (vedi sopra), API Instagram ancora irraggiungibile da questo ambiente (stesso errore della settimana precedente).

### 2026-08-03
- Nessun post analizzato — errore di rete (vedi sopra), API Instagram irraggiungibile da questo ambiente.
