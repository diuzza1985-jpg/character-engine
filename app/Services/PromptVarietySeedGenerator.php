<?php
namespace App\Services;

/**
 * Pool generico (config/content_variety.php, nessun riferimento a un personaggio o tema
 * specifico) di piccoli semi imprevedibili offerti al prompt come spunto facoltativo, non come
 * vincolo. Bug reale segnalato dall'utente: a parità di tema/rubrica (es. biblioteca, passeggiata,
 * serata giochi) il contenuto convergeva sempre sulla stessa formulazione, perché nulla nel
 * prompt cambiava davvero da un'occorrenza all'altra dello stesso tema — le uniche difese
 * esistenti (VARIETÀ DI ARGOMENTI/VARIETÀ VISIVA in EditorialBrainService, cooldown dei LifeEvent
 * in LifeEventSelector) evitano la ripetizione letterale o la scelta troppo frequente dello
 * stesso tema, ma non variano la sostanza quando quel tema si ripresenta a distanza di settimane.
 *
 * Estratto a caso ogni chiamata (random_int, non seedato su data/tema): l'imprevedibilità è
 * proprio il punto, non un algoritmo di selezione — per questo niente qui dipende dal contesto
 * del personaggio o dal tema scelto.
 */
class PromptVarietySeedGenerator
{
    /**
     * @param string $group 'testo' o 'immagine' (chiavi di primo livello in
     *   config/content_variety.php) — pescati indipendentemente così un seme casuale non
     *   condiziona insieme sia la caption che il prompt_immagine, riducendo la combinatoria.
     * @return array<string, string> categoria => seme scelto a caso in quella categoria
     */
    public function generate(string $group = 'testo'): array
    {
        $pools = config("content_variety.{$group}", []);
        $seeds = [];
        foreach ($pools as $category => $options) {
            if (empty($options)) {
                continue;
            }
            $seeds[$category] = $options[random_int(0, count($options) - 1)];
        }
        return $seeds;
    }

    /**
     * Blocco di testo pronto per un prompt, presentato esplicitamente come facoltativo: forzarlo
     * quando non si adatta al tema di oggi produrrebbe contenuti innaturali quanto la ripetizione
     * che deve risolvere, quindi il modello resta libero di ignorare i semi che non calzano.
     */
    public function format(string $group = 'testo'): string
    {
        $seeds = $this->generate($group);
        if (empty($seeds)) {
            return '';
        }

        $lines = array_map(fn ($seed) => "- {$seed}", array_values($seeds));

        if ($group === 'immagine') {
            return "\n=== SPUNTI VISIVI CASUALI PER OGGI (facoltativi, per prompt_immagine) ===\n"
                . "Usali solo per variare inquadratura/luce/composizione se hanno senso con la scena di oggi, ignora gli altri: non sono ingredienti obbligatori, servono solo a evitare che la stessa idea produca sempre la stessa foto.\n"
                . implode("\n", $lines) . "\n";
        }

        return "\n=== SPUNTI CASUALI PER OGGI (facoltativi) ===\n"
            . "Usa solo quelli che si adattano con naturalezza a quello che stai scrivendo, ignora gli altri: non sono ingredienti obbligatori, servono solo a evitare che lo stesso tema prenda sempre la stessa forma.\n"
            . implode("\n", $lines) . "\n";
    }
}
