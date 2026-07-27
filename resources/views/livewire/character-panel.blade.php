<div class="card" x-data="{ tab: 'identita' }">
    <p class="eyebrow">Il tuo personaggio</p>
    <h1>{{ $character->name ?? 'Il tuo personaggio' }}, in un pannello</h1>
    <p class="subtitle">Da qui puoi rivedere e modificare ogni categoria quando vuoi. Le immagini e i contenuti restano bloccati finché non acquisti crediti.</p>

    @if(session('panel_saved'))
        <div class="serious-note">
            <span>✅</span>
            <div>Salvato.</div>
        </div>
    @endif

    @unless($character)
        <div class="ig-banner">
            <span>⏳</span>
            <span>Non hai ancora un personaggio in elaborazione — completa il <a href="{{ route('character.create') }}" style="color:#B39BFF;">questionario di creazione</a> per iniziare.</span>
        </div>
    @endunless

    <div class="tabbar">
        <div class="tab" :class="{ active: tab === 'identita' }" x-on:click="tab = 'identita'">Identità</div>
        <div class="tab" :class="{ active: tab === 'personalita' }" x-on:click="tab = 'personalita'">Personalità</div>
        <div class="tab" :class="{ active: tab === 'voce' }" x-on:click="tab = 'voce'">Come comunica</div>
        <div class="tab" :class="{ active: tab === 'umorismo' }" x-on:click="tab = 'umorismo'">Umorismo</div>
        <div class="tab" :class="{ active: tab === 'aspetto' }" x-on:click="tab = 'aspetto'">Aspetto</div>
        <div class="tab" :class="{ active: tab === 'vita' }" x-on:click="tab = 'vita'">Vita quotidiana</div>
        <div class="tab locked" :class="{ active: tab === 'assets' }" x-on:click="tab = 'assets'">🔒 Assets & Immagini</div>
    </div>

    @if($character)
        <div x-show="tab === 'identita'">
            <div class="field-label">Nome</div>
            <input type="text" class="text-input" wire:model="name" maxlength="255">
            @error('name') <p class="field-hint" style="color:#F58E4E;">{{ $message }}</p> @enderror

            <div class="field-label" style="margin-top:22px;">In una frase, chi è</div>
            <input type="text" class="text-input" wire:model="oneLiner" maxlength="120">
            @error('oneLiner') <p class="field-hint" style="color:#F58E4E;">{{ $message }}</p> @enderror

            <div class="actions"><span></span><button class="btn-primary" wire:click="saveIdentita">Salva</button></div>
        </div>

        <div x-show="tab === 'personalita'" style="display:none;">
            <div class="field-label">Temperamento, valori e antipatie</div>
            <p class="field-hint">Testo libero — descrive il carattere del personaggio, usato dal cervello editoriale per scrivere in modo coerente.</p>
            <textarea class="text-input" rows="10" wire:model="valoriContent" style="resize:vertical; font-family:var(--font-body); line-height:1.5;"></textarea>
            <div class="actions"><span></span><button class="btn-primary" wire:click="savePersonalita">Salva</button></div>
        </div>

        <div x-show="tab === 'voce'" style="display:none;">
            <div class="field-label">Tono e voce</div>
            <p class="field-hint">Come parla: formalità, verbosità, uso delle emoji.</p>
            <textarea class="text-input" rows="10" wire:model="voceContent" style="resize:vertical; font-family:var(--font-body); line-height:1.5;"></textarea>
            <div class="actions"><span></span><button class="btn-primary" wire:click="saveVoce">Salva</button></div>
        </div>

        <div x-show="tab === 'umorismo'" style="display:none;">
            <div class="field-label">Umorismo</div>
            <p class="field-hint">Bersagli comici, limiti di sicurezza sulle battute.</p>
            <textarea class="text-input" rows="10" wire:model="umorismoContent" style="resize:vertical; font-family:var(--font-body); line-height:1.5;"></textarea>
            <div class="actions"><span></span><button class="btn-primary" wire:click="saveUmorismo">Salva</button></div>
        </div>

        <div x-show="tab === 'vita'" style="display:none;">
            <div class="field-label">Vita quotidiana</div>
            <p class="field-hint">Con chi vive, ambiente, animali domestici.</p>
            <textarea class="text-input" rows="10" wire:model="famigliaContent" style="resize:vertical; font-family:var(--font-body); line-height:1.5;"></textarea>
            <div class="actions"><span></span><button class="btn-primary" wire:click="saveVita">Salva</button></div>
        </div>

        <div x-show="tab === 'aspetto'" style="display:none;">
            <div class="field-label">Età e corporatura</div>
            <textarea class="text-input" rows="2" wire:model="ageDescription" style="resize:vertical;"></textarea>

            <div class="field-label" style="margin-top:18px;">Viso</div>
            <textarea class="text-input" rows="2" wire:model="faceDescription" style="resize:vertical;"></textarea>

            <div class="field-label" style="margin-top:18px;">Capelli</div>
            <textarea class="text-input" rows="2" wire:model="hairDescription" style="resize:vertical;"></textarea>

            <div class="field-label" style="margin-top:18px;">Stile / guardaroba</div>
            <textarea class="text-input" rows="2" wire:model="wardrobeNotes" style="resize:vertical;"></textarea>

            <div class="field-label" style="margin-top:18px;">Dettaglio riconoscibile</div>
            <textarea class="text-input" rows="2" wire:model="visualRulesText" style="resize:vertical;"></textarea>

            <div class="actions"><span></span><button class="btn-primary" wire:click="saveAspetto">Salva</button></div>
        </div>
    @else
        @foreach(['identita', 'personalita', 'voce', 'umorismo', 'aspetto', 'vita'] as $emptyTab)
            <div x-show="tab === '{{ $emptyTab }}'" @if($emptyTab !== 'identita') style="display:none;" @endif>
                <div class="generic-pane">Qui potrai rivedere e arricchire questa sezione quando avrai un personaggio.</div>
            </div>
        @endforeach
    @endif

    <div x-show="tab === 'assets'" style="display:none;">
        <div class="ig-banner">
            <span>🔗</span>
            <span>Nessun contenuto verrà generato finché non acquisti crediti — puoi collegare Instagram fin da ora senza rischi.</span>
        </div>
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
