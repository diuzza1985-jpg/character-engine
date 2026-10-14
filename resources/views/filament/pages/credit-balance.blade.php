<x-filament-panels::page>
    <div style="display:flex; flex-direction:column; gap:24px;">

        {{-- fal.ai: saldo reale --}}
        <x-filament::section>
            <x-slot name="heading">fal.ai (generazione immagini)</x-slot>

            @if (! ($fal['configured'] ?? false))
                <p style="opacity:.7;">
                    Non configurato: manca <code>FAL_BILLING_API_KEY</code> nel <code>.env</code>.
                    Va creata una nuova chiave fal.ai con permesso <strong>billing:usage:read</strong>
                    (preset BILLING o READONLY, dashboard fal.ai → API Keys) — diversa da
                    <code>FAL_API_KEY</code>, che resta quella usata per generare le immagini.
                </p>
            @elseif (isset($fal['error']))
                <x-filament::badge color="danger">Errore</x-filament::badge>
                <p style="margin-top:8px;">{{ $fal['error'] }}</p>
            @else
                <div style="display:flex; align-items:baseline; gap:12px;">
                    <span style="font-size:2rem; font-weight:700;">${{ number_format($fal['balance_usd'], 2) }}</span>
                    <x-filament::badge :color="$fal['below_threshold'] ? 'danger' : 'success'">
                        {{ $fal['below_threshold'] ? 'Sotto soglia' : 'Ok' }}
                    </x-filament::badge>
                </div>
                <p style="opacity:.7; margin-top:4px;">
                    Soglia di avviso: ${{ number_format(config('services.fal.low_balance_threshold_usd'), 2) }}.
                    Sotto zero, fal.ai blocca l'account ("User is locked. Reason: TOP_UP.") e ogni
                    generazione di immagini fallisce finché non si ricarica.
                </p>
            @endif
        </x-filament::section>

        {{-- OpenAI: nessun vero saldo esposto via API, solo spesa del mese vs budget di guardia --}}
        <x-filament::section>
            <x-slot name="heading">OpenAI (testo)</x-slot>

            @if (! ($openAi['configured'] ?? false))
                <p style="opacity:.7;">
                    Non configurato: manca <code>OPENAI_ADMIN_API_KEY</code> nel <code>.env</code>.
                    Va creata una Admin API key da
                    platform.openai.com/settings/organization/admin-keys con <strong>solo</strong>
                    lo scope <strong>api.usage.read</strong> selezionato — diversa da
                    <code>OPENAI_API_KEY</code>, che resta quella usata per generare i testi.
                </p>
            @elseif (isset($openAi['error']))
                <x-filament::badge color="danger">Errore</x-filament::badge>
                <p style="margin-top:8px;">{{ $openAi['error'] }}</p>
            @else
                <div style="display:flex; align-items:baseline; gap:12px;">
                    <span style="font-size:2rem; font-weight:700;">${{ number_format($openAi['spent_usd'], 2) }}</span>
                    <span style="opacity:.7;">/ ${{ number_format($openAi['budget_usd'], 2) }} ({{ $openAi['pct_used'] }}%)</span>
                    <x-filament::badge :color="$openAi['over_threshold'] ? 'danger' : 'success'">
                        {{ $openAi['over_threshold'] ? 'Sopra soglia' : 'Ok' }}
                    </x-filament::badge>
                </div>
                <p style="opacity:.7; margin-top:4px;">
                    Spesa dal {{ \Illuminate\Support\Carbon::parse($openAi['period_start'])->format('d/m/Y') }} a oggi.
                    OpenAI non espone un saldo prepagato residuo per le API key standard: questo è
                    un budget mensile impostato a mano (<code>OPENAI_MONTHLY_BUDGET_USD</code>), non
                    un saldo reale come quello di fal.ai sopra — l'avviso scatta quando la spesa lo
                    supera, non quando un saldo scende a zero.
                </p>
            @endif
        </x-filament::section>

        <p style="opacity:.6; font-size:.85rem;">
            Un'email di avviso parte da sola (<code>credits:check-balances</code>, schedulato) quando
            fal.ai scende sotto soglia o la spesa OpenAI supera il budget di guardia — al massimo una
            ogni 24 ore, a <code>{{ config('mail.alert_to') ?: '(MAIL_ALERT_TO non configurato)' }}</code>.
        </p>

    </div>
</x-filament-panels::page>
