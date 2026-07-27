{{--
    multi=true: $model è un array Alpine (push/filter). multi=false: $model è uno scalare.
    defaultLocked: valori dei "limiti di sicurezza" precompilati e non deselezionabili (solo
    aggiungibili), stesso principio già nella bible di Sofia — click su quei chip è un no-op.
    allowCustom (solo con multi=true): aggiunge un chip "+ aggiungi altro" che apre un input
    inline — nel prototipo originale quel chip esisteva già ma senza alcun handler, non era
    mai stato collegato a nulla (bug segnalato dall'utente durante la revisione).
--}}
@props(['items', 'model', 'multi' => true, 'defaultLocked' => [], 'allowCustom' => false])
<div class="chip-row">
    @foreach($items as $item)
        @php($isLocked = in_array($item['value'], $defaultLocked))
        <div
            class="chip @if($isLocked) checked-default @endif"
            @if($multi)
                :class="{ selected: {{ $model }}.includes(@js($item['value'])) }"
                x-on:click="@if($isLocked) null @else {{ $model }}.includes(@js($item['value'])) ? {{ $model }} = {{ $model }}.filter(v => v !== @js($item['value'])) : {{ $model }}.push(@js($item['value'])) @endif"
            @else
                :class="{ selected: {{ $model }} === @js($item['value']) }"
                x-on:click="{{ $model }} = @js($item['value'])"
            @endif
        >{{ $item['label'] }}</div>
    @endforeach

    @if($allowCustom)
        <template x-for="customItem in {{ $model }}.filter(v => !@js(array_column($items, 'value')).includes(v))" :key="customItem">
            <div class="chip selected" x-text="customItem" x-on:click="{{ $model }} = {{ $model }}.filter(v => v !== customItem)"></div>
        </template>
        <div x-data="{ adding: false, customValue: '' }">
            <div class="chip" x-show="!adding" x-on:click="adding = true">+ aggiungi altro</div>
            <span x-show="adding" style="display:inline-flex; gap:6px; align-items:center;">
                <input
                    type="text"
                    class="text-input"
                    style="display:inline-block; width:160px; padding:8px 12px;"
                    x-model="customValue"
                    x-on:keydown.enter.prevent="if (customValue.trim()) { {{ $model }}.push(customValue.trim()); customValue = ''; adding = false }"
                >
                <button type="button" class="chip" x-on:click="if (customValue.trim()) { {{ $model }}.push(customValue.trim()); customValue = ''; adding = false }">✓</button>
            </span>
        </div>
    @endif
</div>
