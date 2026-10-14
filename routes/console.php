<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('instagram:reply-comments sofia')->hourly();
// Bug reale del 13 settembre 2026 (Post#58/59/60): il rinnovo del token esisteva già come metodo
// (InstagramGraphService::refreshLongLivedToken) ma non era mai schedulato — il token (60 giorni
// di validità) scadeva in silenzio. ->daily() basta: il comando rinnova già tutto ciò che scade
// entro 7 giorni (RefreshInstagramTokens::REFRESH_WINDOW_DAYS), non serve un controllo più fitto.
Schedule::command('instagram:refresh-tokens')->daily();
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

// Avviso saldo fal.ai/OpenAI via email (richiesta utente, 13 ottobre 2026). Ogni 6 ore basta: il
// comando stesso non manda più di un'email ogni 24h mentre si resta sotto soglia
// (CheckCreditBalances::ALERT_COOLDOWN_HOURS), questa cadenza serve solo a non far passare troppo
// tempo dal momento in cui si scende sotto soglia a quando arriva il primo avviso.
Schedule::command('credits:check-balances')->everySixHours();

// Sostituisce la routine cloud "Osservazione bersagli comici" (14 ottobre 2026, vedi
// CheckComicTargets): stesso giorno e ora, lunedì mattina, sulle caption già nel DB.
Schedule::command('character:check-comic-targets sofia')->weeklyOn(1, '08:00');
