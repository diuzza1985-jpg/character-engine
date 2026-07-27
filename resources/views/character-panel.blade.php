@extends('layouts.public')

@section('title', 'Il tuo personaggio — Character Engine')

@section('content')
<div class="card" x-data="{ tab: 'assets' }">
    <p class="eyebrow">Dopo la registrazione</p>
    <h1>Il tuo personaggio, in un pannello</h1>
    <p class="subtitle">Da qui puoi rivedere e arricchire ogni categoria quando vuoi. Le immagini e i contenuti restano bloccati finché non acquisti crediti.</p>

    @if($character)
        <div class="ig-banner">
            <span>🔗</span>
            <span><strong>{{ $character->name }}</strong> è pronto. Nessun contenuto verrà generato finché non acquisti crediti — puoi collegare Instagram fin da ora senza rischi.</span>
        </div>
    @else
        <div class="ig-banner">
            <span>⏳</span>
            <span>Il tuo personaggio è ancora in elaborazione. Ricarica la pagina tra qualche istante.</span>
        </div>
    @endif

    <div class="tabbar">
        <div class="tab" :class="{ active: tab === 'identita' }" x-on:click="tab = 'identita'">Identità</div>
        <div class="tab" :class="{ active: tab === 'personalita' }" x-on:click="tab = 'personalita'">Personalità</div>
        <div class="tab" :class="{ active: tab === 'voce' }" x-on:click="tab = 'voce'">Come comunica</div>
        <div class="tab" :class="{ active: tab === 'umorismo' }" x-on:click="tab = 'umorismo'">Umorismo</div>
        <div class="tab" :class="{ active: tab === 'aspetto' }" x-on:click="tab = 'aspetto'">Aspetto</div>
        <div class="tab" :class="{ active: tab === 'vita' }" x-on:click="tab = 'vita'">Vita quotidiana</div>
        <div class="tab locked" :class="{ active: tab === 'assets' }" x-on:click="tab = 'assets'">🔒 Assets & Immagini</div>
    </div>

    <div x-show="tab === 'identita'"><div class="generic-pane">Qui puoi rivedere e arricchire Identità quando vuoi.</div></div>
    <div x-show="tab === 'personalita'"><div class="generic-pane">Qui puoi rivedere e arricchire Personalità quando vuoi.</div></div>
    <div x-show="tab === 'voce'"><div class="generic-pane">Qui puoi rivedere e arricchire Come comunica quando vuoi.</div></div>
    <div x-show="tab === 'umorismo'"><div class="generic-pane">Qui puoi rivedere e arricchire Umorismo quando vuoi.</div></div>
    <div x-show="tab === 'aspetto'"><div class="generic-pane">Qui puoi rivedere e arricchire Aspetto quando vuoi.</div></div>
    <div x-show="tab === 'vita'"><div class="generic-pane">Qui puoi rivedere e arricchire Vita quotidiana quando vuoi.</div></div>

    <div x-show="tab === 'assets'">
        @if($creditBalance > 0)
            <div class="locked-pane" style="background:rgba(63,207,142,.08); border-color:rgba(63,207,142,.35);">
                <div class="coin-icon">🪙</div>
                <h3>Saldo disponibile: {{ $creditBalance }} crediti</h3>
                <p>Puoi generare volto, pose, post e reel: ogni generazione consuma crediti dal tuo saldo, nessun addebito automatico.</p>
            </div>
        @else
            <div class="locked-pane">
                <div class="coin-icon">🪙</div>
                <h3>Genera il volto e i contenuti del tuo personaggio</h3>
                <p>Per generare immagini, post e video serve un saldo di crediti. Ogni generazione (viso, pose, post, reel) consuma crediti dal tuo saldo — nessun addebito automatico, nessuna sorpresa.</p>
                <button class="btn-warn" type="button">Acquista crediti →</button>
            </div>
        @endif
    </div>
</div>
@endsection
