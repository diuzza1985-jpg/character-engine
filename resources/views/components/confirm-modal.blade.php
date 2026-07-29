{{--
    Modal di conferma generico, Alpine puro (nessun round-trip Livewire per aprire/chiudere).
    $show è un'espressione booleana nello scope Alpine del contenitore (es. "confirmDelete !== null").
    $confirmClick è l'espressione eseguita al click su conferma (in genere una chiamata $wire.*
    seguita dal reset dello stato Alpine che controlla $show).
--}}
@props(['show', 'title', 'confirmClick', 'confirmLabel' => 'Conferma', 'danger' => false])
<div
    x-show="{{ $show }}"
    x-cloak
    x-transition
    style="position:fixed; inset:0; background:rgba(5,7,15,.72); display:flex; align-items:center; justify-content:center; z-index:60; padding:20px;"
    x-on:click="{{ $show }} = false"
>
    <div class="card" style="max-width:420px; width:100%;" x-on:click.stop>
        <div style="font-family:var(--font-display); font-size:19px; font-weight:700; margin:0 0 12px;">{{ $title }}</div>
        <div style="color:var(--ink-soft); font-size:14px; line-height:1.6; margin:0 0 28px;">{{ $slot }}</div>
        <div class="actions" style="margin-top:0;">
            <button type="button" class="btn-ghost" x-on:click="{{ $show }} = false">Annulla</button>
            <button type="button" class="{{ $danger ? 'btn-warn' : 'btn-primary' }}" x-on:click="{{ $confirmClick }}">{{ $confirmLabel }}</button>
        </div>
    </div>
</div>
