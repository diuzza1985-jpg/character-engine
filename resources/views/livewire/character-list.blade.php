<div class="card" x-data="{ confirmDelete: null }">
    <p class="eyebrow">I tuoi personaggi</p>
    <h1>Menu personaggi</h1>
    <p class="subtitle">Da qui puoi riprendere una bozza, rivedere un personaggio già creato o iniziarne uno nuovo — un account può averne più di uno, collegati anche per parentela.</p>

    @if($trashedCount > 0)
        <p class="field-hint" style="margin-top:-14px;"><a href="{{ route('character.trash') }}" style="color:var(--ink-soft);">Cestino ({{ $trashedCount }})</a></p>
    @endif

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
                        <span
                            x-on:click="confirmDelete = { type: 'draft', id: '{{ $draft->id }}', name: @js($draft->name ?: 'Senza nome ancora') }"
                            style="cursor:pointer; color:#F58E4E; margin-left:10px; font-weight:700; font-size:13px;"
                        >Elimina</span>
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
                    <span class="k">{{ $character->name }}@if($character->kind) <span class="field-hint" style="display:inline; font-weight:600;">({{ $character->kind === 'animale' ? '🐾 animale' : '🧑 persona' }})</span>@endif</span>
                    <span class="v">
                        <span class="counter @if($character->status === 'active') ok @endif">{{ $statusLabel }}</span>
                        <a href="{{ route('character.show', $character) }}" style="color:#B39BFF; margin-left:10px;">Apri →</a>
                        <span
                            x-on:click="confirmDelete = { type: 'character', id: {{ $character->id }}, name: @js($character->name) }"
                            style="cursor:pointer; color:#F58E4E; margin-left:10px; font-weight:700; font-size:13px;"
                        >Elimina</span>
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    <div class="actions">
        <span></span>
        <a href="{{ route('character.create') }}" class="btn-primary">+ Nuovo personaggio</a>
    </div>

    <x-confirm-modal
        show="confirmDelete !== null"
        close="confirmDelete = null"
        title="Conferma eliminazione"
        confirm-click="confirmDelete?.type === 'character' ? $wire.deleteCharacter(confirmDelete.id) : $wire.deleteDraft(confirmDelete.id); confirmDelete = null"
        confirm-label="Elimina"
        :danger="true"
    >
        <template x-if="confirmDelete?.type === 'character'">
            <span>Stai per eliminare <strong x-text="confirmDelete?.name"></strong>. Il personaggio verrà spostato nel cestino: potrai ripristinarlo o eliminarlo definitivamente in un secondo momento.</span>
        </template>
        <template x-if="confirmDelete?.type === 'draft'">
            <span>Stai per eliminare la bozza <strong x-text="confirmDelete?.name"></strong>. L'eliminazione è <strong>definitiva</strong>: non potrai recuperarla.</span>
        </template>
    </x-confirm-modal>
</div>
