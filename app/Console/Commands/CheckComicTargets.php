<?php
namespace App\Console\Commands;
use App\Mail\ComicTargetRepetitionMail;
use App\Models\Character;
use App\Models\Post;
use App\Services\OpenAiTextService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * 14 ottobre 2026: sostituisce la routine cloud "Osservazione bersagli comici Sofia" (disattivata,
 * fallita 11 settimane su 11: dall'ambiente cloud graph.instagram.com non era raggiungibile).
 * Stesso scopo — capire se il personaggio ripete sempre lo stesso bersaglio comico — ma letto
 * dalle caption pubblicate già nel DB, senza API Instagram né token copiati a mano.
 *
 * Ogni post viene classificato una sola volta (comic_target_checked_at); il report in
 * storage/app/private/reports/ viene riscritto a ogni esecuzione, e se un bersaglio supera la
 * soglia nella finestra (config/comic_targets.php) parte un avviso a MAIL_ALERT_TO.
 */
class CheckComicTargets extends Command
{
    protected $signature = 'character:check-comic-targets {slug=sofia}';

    protected $description = 'Classifica il bersaglio comico dei post pubblicati e segnala le ripetizioni';

    // Caption per singola chiamata: il primo giro deve recuperare tutto lo storico.
    private const BATCH_SIZE = 25;

    public function handle(OpenAiTextService $openAi): int
    {
        $slug = $this->argument('slug');
        $targets = config("comic_targets.characters.{$slug}");
        $character = Character::where('slug', $slug)->first();
        if (! $character || ! $targets) {
            $this->error("Personaggio '{$slug}' inesistente o senza bersagli comici in config/comic_targets.php.");

            return self::FAILURE;
        }

        $pending = $this->publishedPosts($character)->whereNull('comic_target_checked_at')->orderBy('published_at')->get();
        foreach ($pending->chunk(self::BATCH_SIZE) as $chunk) {
            $this->classify($openAi, $chunk, $targets);
        }
        $this->info("Post classificati in questo giro: {$pending->count()}.");

        $windowDays = (int) config('comic_targets.window_days');
        $recent = $this->publishedPosts($character)
            ->whereNotNull('comic_target')
            ->where('published_at', '>=', now()->subDays($windowDays))
            ->orderBy('published_at')
            ->get();
        $repetitions = $recent->groupBy('comic_target')
            ->filter(fn (Collection $posts) => $posts->count() >= (int) config('comic_targets.repeat_threshold'));

        $this->writeReport($character, $repetitions, $windowDays);

        if ($repetitions->isNotEmpty()) {
            $this->warn('Ripetizioni: ' . $repetitions->map->count()->toJson(JSON_UNESCAPED_UNICODE));
            if ($to = config('mail.alert_to')) {
                Mail::to($to)->send(new ComicTargetRepetitionMail($character->name, $repetitions, $windowDays));
            } else {
                Log::warning('character:check-comic-targets: ripetizioni trovate ma MAIL_ALERT_TO non configurato.');
            }
        }

        return self::SUCCESS;
    }

    private function publishedPosts(Character $character)
    {
        // Le story di condivisione automatica (related_post_id) ripetono il post originale.
        return Post::where('character_id', $character->id)
            ->where('status', 'published')
            ->whereNull('related_post_id')
            ->whereNotNull('caption');
    }

    private function classify(OpenAiTextService $openAi, Collection $posts, array $targets): void
    {
        $elenco = collect($targets)->map(fn ($t) => "- {$t}")->implode("\n");
        $captions = $posts->map(fn (Post $p) => "[{$p->id}] " . $p->caption)->implode("\n\n");

        $result = $openAi->generateJson(<<<TXT
Per ciascuna caption Instagram qui sotto, indica su quale bersaglio comico scherza, scegliendo
ESCLUSIVAMENTE una voce di questo elenco, copiata identica:
{$elenco}

Se la caption non è una battuta, o scherza su qualcosa che non è nell'elenco, usa "nessuno".
Usa il buon senso, non serve una corrispondenza testuale esatta.

Rispondi solo con JSON: {"posts": [{"id": <numero tra parentesi quadre>, "target": "<voce o nessuno>"}]}

{$captions}
TXT);

        $byId = collect($result['posts'] ?? [])->keyBy('id');
        foreach ($posts as $post) {
            $target = $byId->get($post->id)['target'] ?? null;
            if ($target === null) {
                continue; // risposta incompleta: resta da analizzare al prossimo giro
            }
            $post->update([
                'comic_target' => in_array($target, $targets, true) ? $target : null,
                'comic_target_checked_at' => now(),
            ]);
        }
    }

    private function writeReport(Character $character, Collection $repetitions, int $windowDays): void
    {
        $counts = $this->publishedPosts($character)->whereNotNull('comic_target_checked_at')
            ->selectRaw('comic_target, count(*) as n')->groupBy('comic_target')->orderByDesc('n')->pluck('n', 'comic_target');

        $lines = ["# Bersagli comici — {$character->name}", '', 'Ultimo controllo: ' . now()->format('Y-m-d H:i'), ''];
        if ($repetitions->isEmpty()) {
            $lines[] = "Nessuna ripetizione negli ultimi {$windowDays} giorni.";
        } else {
            $lines[] = "## ⚠ Ripetizioni negli ultimi {$windowDays} giorni";
            foreach ($repetitions as $target => $posts) {
                $lines[] = "- **{$target}**: " . $posts->map(fn (Post $p) => $p->published_at->format('d/m') . " (post #{$p->id})")->implode(', ');
            }
        }
        $lines[] = '';
        $lines[] = '## Totale storico';
        foreach ($counts as $target => $n) {
            $lines[] = '- ' . ($target ?: 'nessuno / non umoristico') . ": {$n}";
        }

        Storage::disk('local')->put("reports/bersagli-comici-{$character->slug}.md", implode("\n", $lines) . "\n");
    }
}
