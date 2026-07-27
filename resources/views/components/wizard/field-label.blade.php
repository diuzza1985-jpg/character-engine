{{--
    Riusato in tutti gli screen del wizard. counterExpr/counterOkExpr sono espressioni JS grezze
    (non stringhe Blade) perché il contatore deve aggiornarsi live sul click di chip/tile, che
    resta puramente Alpine lato client — non c'è un round-trip Livewire a ogni tap.
--}}
@props(['counterExpr' => null, 'counterOkExpr' => 'false', 'hint' => null])
<div class="field-label">
    <span>{{ $slot }}</span>
    @if($counterExpr)
        <span class="counter" :class="{ ok: {{ $counterOkExpr }} }" x-text="{{ $counterExpr }}"></span>
    @endif
</div>
@if($hint)
    <p class="field-hint">{{ $hint }}</p>
@endif
