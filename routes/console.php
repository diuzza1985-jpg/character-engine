<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('instagram:reply-comments sofia')->hourly();
Schedule::command('schedule:materialize-weekly')->dailyAt('00:05');
Schedule::command('schedule:process-due')->everyFifteenMinutes();
// Orario da tarare in seguito (dopo che sappiamo quando girano generazione/pubblicazione).
Schedule::command('character:live-tick')->daily();
Schedule::command('character-drafts:prune-expired')->daily();
// Formato "tema_caldo" (documento temi-attualità, sez. 3): "ogni poche ore" per restare
// ragionevoli sul costo delle chiamate LLM di giudizio (YoutubeTrendingService costa poche unità
// per ciclo, non è il vincolo) — non serve una cadenza più fitta, un tema "caldo" resta valido
// per giorni (CheckHotTopics::MAX_TOPIC_AGE_DAYS).
Schedule::command('character:check-hot-topics')->everyFourHours();

// Automazione giornaliera (richiesta dall'utente, 31 luglio 2026): ogni personaggio attivo decide
// da solo se e cosa pubblicare oggi, incluso l'orario (EditorialBrainService::orario_pubblicazione)
// — indipendente da WeeklyScheduleSlot/schedule:materialize-weekly sopra (quello resta per chi
// vuole un orario fisso deciso a mano). Orario di esecuzione in ora italiana esplicita
// (->timezone), non quella UTC di default del server: deve girare abbastanza presto da lasciare
// margine prima della finestra di pubblicazione scelta dal cervello editoriale (7:00-22:00).
Schedule::command('character:plan-daily-post')->timezone('Europe/Rome')->dailyAt('06:00');
// Stessa cadenza di schedule:process-due sopra: qui non si genera nulla, solo pubblicare i post
// già pronti (scheduled_at) il cui orario è arrivato.
Schedule::command('character:publish-due-posts')->everyFifteenMinutes();
