{{--
    Nuovo componente (nessun riferimento nel prototipo, screen "components" mancante dal file
    consegnato): stesso linguaggio visivo di chip/tile (bordi/superfici della palette).
--}}
@props(['model', 'placeholder' => '', 'maxlength' => null])
<input
    type="text"
    class="text-input"
    x-model="{{ $model }}"
    placeholder="{{ $placeholder }}"
    @if($maxlength) maxlength="{{ $maxlength }}" @endif
>
