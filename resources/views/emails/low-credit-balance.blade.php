@component('mail::message')
# Saldo in esaurimento

@if ($fal)
## fal.ai (generazione immagini)
@if (isset($fal['error']))
Errore nel leggere il saldo: {{ $fal['error'] }}
@else
Saldo residuo: **${{ number_format($fal['balance_usd'], 2) }}**

Sotto la soglia di guardia (${{ number_format(config('services.fal.low_balance_threshold_usd'), 2) }}). Appena si esaurisce, fal.ai blocca l'account ("User is locked. Reason: TOP_UP.") e ogni generazione di immagini fallisce finché non si ricarica.
@endif
@endif

@if ($openAi)
## OpenAI (testo)
@if (isset($openAi['error']))
Errore nel leggere la spesa: {{ $openAi['error'] }}
@else
Spesa di questo mese ({{ $openAi['period_start'] }} → oggi): **${{ number_format($openAi['spent_usd'], 2) }}** su un budget di guardia di ${{ number_format($openAi['budget_usd'], 2) }} ({{ $openAi['pct_used'] }}%).

OpenAI non espone un vero saldo prepagato residuo per questa chiave: questo è un budget impostato a mano, non un saldo reale come quello di fal.ai sopra.
@endif
@endif

@component('mail::button', ['url' => url('/aiadmin/saldo-crediti')])
Apri il pannello saldo
@endcomponent

Grazie,<br>
{{ config('app.name') }}
@endcomponent
