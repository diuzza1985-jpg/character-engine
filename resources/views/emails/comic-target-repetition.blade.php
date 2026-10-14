@component('mail::message')
# Bersagli comici ripetuti

Negli ultimi {{ $windowDays }} giorni {{ $characterName }} ha scherzato più volte sullo stesso bersaglio:

@foreach ($repetitions as $target => $posts)
- **{{ $target }}**: {{ $posts->map(fn ($p) => $p->published_at->format('d/m'))->implode(', ') }}
@endforeach

Il report completo è in `storage/app/private/reports/`.
@endcomponent
