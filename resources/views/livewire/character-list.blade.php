<div class="card">
    <p class="eyebrow">I tuoi personaggi</p>
    <h1>Menu personaggi</h1>
    <p class="subtitle">Da qui puoi riprendere una bozza, rivedere un personaggio già creato o iniziarne uno nuovo — un account può averne più di uno, collegati anche per parentela.</p>

    @if($drafts->isEmpty() && $characters->isEmpty())
        <div class="ig-banner">
            <span>👋</span>
            <span>Non hai ancora nessun personaggio. <a href="{{ route('character.create') }}" style="color:#B39BFF;">Creane uno con il questionario</a>.</span>
        </div>
    @endif

    @if($drafts->isNotEmpty())
        <div class="field-label">Bozze in corso</div>
        <div class="summary-list">
            @foreach($drafts as $draft)
                <div class="summary-row">
                    <span class="k">{{ $draft->name ?: 'Senza nome ancora' }}</span>
                    <span class="v">
                        <span class="counter">Bozza</span>
                        <a href="{{ route('character.create', ['bozza' => $draft->id]) }}" style="color:#B39BFF; margin-left:10px;">Continua →</a>
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    @if($characters->isNotEmpty())
        <div class="field-label" style="margin-top:28px;">Personaggi</div>
        <div class="summary-list">
            @foreach($characters as $character)
                @php($statusLabel = $character->status === 'active' ? 'Attivo' : 'Salvato')
                <div class="summary-row">
                    <span class="k">{{ $character->name }}</span>
                    <span class="v">
                        <span class="counter @if($character->status === 'active') ok @endif">{{ $statusLabel }}</span>
                        <a href="{{ route('character.show', $character) }}" style="color:#B39BFF; margin-left:10px;">Apri →</a>
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="actions">
        <span></span>
        <a href="{{ route('character.create') }}" class="btn-primary">+ Nuovo personaggio</a>
    </div>
</div>
