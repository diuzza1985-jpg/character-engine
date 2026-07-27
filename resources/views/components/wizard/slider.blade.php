{{--
    Nuovo componente (nessun riferimento nel prototipo, screen "components" mancante dal file
    consegnato): stesso linguaggio visivo della palette (gradiente p1→p3, thumb bianco con
    anello viola), track nativo <input type=range> per accessibilità/tastiera gratuite.
--}}
@props(['model', 'leftLabel', 'rightLabel', 'min' => 0, 'max' => 100])
<div class="slider-group">
    <div class="slider-labels"><span>{{ $leftLabel }}</span><span>{{ $rightLabel }}</span></div>
    <input type="range" min="{{ $min }}" max="{{ $max }}" x-model.number="{{ $model }}">
</div>
