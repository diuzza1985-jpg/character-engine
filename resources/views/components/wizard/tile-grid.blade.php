{{-- Selezione singola stile "tile" (es. obiettivo, umorismo, stile visivo). --}}
@props(['items', 'model', 'wide' => []])
<div class="tile-grid">
    @foreach($items as $item)
        <div
            class="tile @if(in_array($item['value'], $wide)) wide @endif"
            :class="{ selected: {{ $model }} === @js($item['value']) }"
            x-on:click="{{ $model }} = @js($item['value'])"
        >
            @if(!empty($item['icon']))
                <span class="badge" style="background:{{ $item['color'] ?? '#2E3E5C' }};">{{ $item['icon'] }}</span>
            @endif
            {{ $item['label'] }}
        </div>
    @endforeach
</div>
