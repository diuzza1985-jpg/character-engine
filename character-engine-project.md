# Character Engine — Project Log

> Nota: questo file non esisteva nel repository — ricreato qui su richiesta esplicita perché il
> documento di riferimento vive in una cartella cloud collegata non raggiungibile da questo
> ambiente. Non contiene quindi la storia completa del progetto (sezioni 1-12.x citate nelle
> conversazioni precedenti), solo un log a partire da questo punto. Se esiste una versione con la
> numerazione originale, va riconciliata a mano — questo file non inventa una continuità di
> numerazione che non può verificare.

Formato: una sezione per iniziativa/intervento significativo, più recente in cima. Per il
dettaglio completo di un'iniziativa multi-fase, vedi il documento dedicato quando esiste (es.
`character-engine-comparse-mondo-narrativo.md`).

---

## Il mondo intorno al personaggio (comparse, relazioni, mondo narrativo)

Vedi `character-engine-comparse-mondo-narrativo.md` per il piano completo e il dettaglio delle
fasi. Stato a oggi:

- **Fase 0 (investigazione)**: completata. Trovate e corrette le cause reali di due bug osservati
  su Sofia (Fernando sovra-rappresentato come "quello che costruisce"; Sofia quasi sempre a tema
  tech/bug) — un mix di dati di bible da bilanciare (specifico di Sofia, corretto nei suoi
  contenuti) e un bug di codice generico (`TimelineEntry.topic` mai popolato dal flusso attivo,
  corretto per qualunque personaggio). Confermato che le pipeline schedulate già escludono i
  personaggi non attivi, nessun nuovo filtro di stato necessario.
- **Fase 1 (relazioni collegate a Character reali)**: completata.
  `character_relationships.related_character_id`, nessuna nuova colonna di "ruolo" — un
  personaggio "secondario" resta un `Character` normale in status draft. Verificato con un caso
  reale (Fernando creato come Character vero, collegato e confermato dormiente su tutta la
  pipeline).
- **Fase 2 (wizard persone/animali)**: completata. Nuovo step "mondo" biforcato persona/animale,
  `character_drafts.world_characters`, `ConvertCharacterDraftToCharacter::syncWorldCharacters()`
  crea/aggiorna un `Character` reale in draft per ciascuna voce + `CharacterVisualProfile` (solo
  `face_description`, verificato che il resto degrada pulito via `array_filter`) +
  `CharacterRelationship` + `CharacterKinship` (solo per le persone). Verificato in tinker con
  dati reali incluso update-in-place su riconversione; 64 test verdi; nessuna stringa
  personaggio-specifica nel diff. "Foto opzionale" rimandata alla Fase 4 (serve prima il
  meccanismo di consenso).
- **Fase 3 (generazione multi-soggetto)**: da fare.
- **Fase 4 (asset caricati dall'utente)**: da fare.

Principio guida stabilito in questa iniziativa (vincolante per tutto il progetto, non solo per
questa funzionalità): la bible di un personaggio è dato, può contenere riferimenti specifici a
lui/lei; il codice del cervello editoriale e di ogni servizio condiviso deve restare
completamente generico, mai un riferimento hardcoded a un personaggio o relazione specifica.
