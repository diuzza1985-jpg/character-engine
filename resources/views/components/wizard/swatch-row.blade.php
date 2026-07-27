{{-- Selezione singola a cerchi colorati (es. colore capelli/occhi). --}}
@props(['items', 'model'])
<div class="chip-row">
    @foreach($items as $item)
        <div
            class="swatch"
            style="background:{{ $item['color'] }};"
            :class="{ selected: {{ $model }} === @js($item['value']) }"
            x-on:click="{{ $model }} = @js($item['value'])"
            title="{{ $item['label'] }}"
        ></div>
    @endforeach
</div>
