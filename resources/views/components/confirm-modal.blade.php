{{--
    Modal di conferma generico, Alpine puro (nessun round-trip Livewire per aprire/chiudere).
    $show è un'espressione booleana nello scope Alpine del contenitore (es. "confirmDelete !== null").
    $close è l'espressione ESEGUITA per chiuderlo (es. "confirmDelete = null") — deve restare
    distinta da $show: $show può essere un confronto ("x !== null"), a cui non si può assegnare
    direttamente (bug reale corretto qui: "x !== null = false" non è JS valido, il click su
    Annulla/sfondo non faceva nulla).
    $confirmClick è l'espressione eseguita al click su conferma (in genere una chiamata $wire.*
    seguita dalla stessa chiusura di $close).
--}}
@props(['show', 'close', 'title', 'confirmClick', 'confirmLabel' => 'Conferma', 'danger' => false])
<div
    x-show="{{ $show }}"
    x-cloak
    x-transition
    style="position:fixed; inset:0; background:rgba(5,7,15,.72); z-index:60; display:flex; align-items:center; justify-content:center; padding:20px;"
    x-on:click="{{ $close }}"
>
    <div class="card" style="width:420px; max-width:100%; margin:auto;" x-on:click.stop>
        <div style="font-family:var(--font-display); font-size:19px; font-weight:700; margin:0 0 12px;">{{ $title }}</div>
        <div style="color:var(--ink-soft); font-size:14px; line-height:1.6; margin:0 0 28px;">{{ $slot }}</div>
        <div class="actions" style="margin-top:0;">
            <button type="button" class="btn-ghost" x-on:click="{{ $close }}">Annulla</button>
            <button type="button" class="{{ $danger ? 'btn-warn' : 'btn-primary' }}" x-on:click="{{ $confirmClick }}">{{ $confirmLabel }}</button>
        </div>
    </div>
</div>
