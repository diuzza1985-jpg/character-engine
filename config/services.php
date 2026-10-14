<?php
return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],
    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],
    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'text_model' => env('OPENAI_TEXT_MODEL', 'gpt-5.5'),
        // Chiave SEPARATA e più privilegiata (una Admin API key, non la project key sopra):
        // creata da platform.openai.com/settings/organization/admin-keys con solo lo scope
        // "api.usage.read" selezionato — l'unica che può leggere /v1/organization/costs
        // (verificato il 13/10/2026: la project key sopra risponde 403 "Missing scopes:
        // api.usage.read" su quell'endpoint). Mai usarla in OpenAiTextService: non deve mai
        // poter generare contenuti, solo leggerne la spesa.
        'admin_key' => env('OPENAI_ADMIN_API_KEY'),
        // OpenAI non espone, per le API key standard, un vero saldo prepagato residuo — solo la
        // spesa storica per bucket temporale (/v1/organization/costs). Il budget qui è una soglia
        // "di guardia" impostata a mano: l'avviso scatta quando la spesa del mese di calendario
        // in corso la supera, non quando un saldo reale scende sotto zero (quello, a differenza
        // di fal.ai sotto, qui non esiste via API).
        'monthly_budget_usd' => (float) env('OPENAI_MONTHLY_BUDGET_USD', 50),
        'budget_alert_threshold_pct' => (float) env('OPENAI_BUDGET_ALERT_THRESHOLD_PCT', 80),
    ],
    'fal' => [
        'key' => env('FAL_API_KEY'),
        'model' => env('FAL_MODEL', 'fal-ai/flux-pro/kontext'),
        // Chiave separata con permesso "billing:usage:read" (preset BILLING o READONLY nel
        // pannello fal.ai, mai FULL: non serve il permesso di generare) richiesta per leggere
        // https://api.fal.ai/v1/account/billing — FAL_API_KEY sopra resta quella delle chiamate
        // di inferenza vere e proprie (verificato il 13/10/2026: quella chiave risponde 403
        // "not permitted" su quell'endpoint, non ha questo scope).
        'billing_key' => env('FAL_BILLING_API_KEY'),
        'low_balance_threshold_usd' => (float) env('FAL_LOW_BALANCE_THRESHOLD_USD', 10),
    ],
    'instagram' => [
        'app_id' => env('INSTAGRAM_APP_ID'),
        'app_secret' => env('INSTAGRAM_APP_SECRET'),
        'redirect_uri' => env('INSTAGRAM_REDIRECT_URI'),
    ],
    'newsdata' => [
        'key' => env('NEWSDATA_API_KEY'),
    ],
    // Dormiente: RedditService (commit e290b7c) resta pronto per quando/se arriva l'approvazione
    // OAuth (Reddit ha chiuso sia l'endpoint pubblico anonimo, maggio 2026, sia la registrazione
    // app self-service, novembre 2025) — non è usato da nessun comando attivo oggi
    // (character:check-hot-topics usa YoutubeTrendingService), ma il codice non va perso.
    'reddit' => [
        'client_id' => env('REDDIT_CLIENT_ID'),
        'client_secret' => env('REDDIT_CLIENT_SECRET'),
        'user_agent' => env('REDDIT_USER_AGENT', 'CharacterEngineBot/1.0 (script; read-only)'),
    ],
    'youtube' => [
        'key' => env('YOUTUBE_API_KEY'),
    ],
];
