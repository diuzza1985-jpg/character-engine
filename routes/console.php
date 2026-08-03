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
// vuole un orario fisso deciso a mano).
// Ogni 15 minuti, NON più dailyAt('06:00'): bug reale osservato su questa macchina di sviluppo
// (WSL2/Windows, non un server sempre acceso) — un trigger a un singolo minuto esatto, se perso
// perché l'ambiente era sospeso esattamente in quel momento, salta l'intera giornata senza nessun
// recupero (successo per 2 giorni di fila). Il comando stesso ora controlla "ho già pianificato
// oggi per questo personaggio?" (PlanDailyPost::alreadyPlannedToday), quindi ricontrollare spesso
// è idempotente/economico (nessuna chiamata GPT ripetuta lo stesso giorno) e rende l'automazione
// resiliente a un ambiente che si sospende: appena si risveglia, il giro successivo se ne accorge.
Schedule::command('character:plan-daily-post')->timezone('Europe/Rome')->everyFifteenMinutes();
// Stessa cadenza di schedule:process-due sopra: qui non si genera nulla, solo pubblicare i post
// già pronti (scheduled_at) il cui orario è arrivato.
Schedule::command('character:publish-due-posts')->everyFifteenMinutes();
