<div class="card" x-data="{ confirmForceDelete: null }">
    <p class="eyebrow"><a href="{{ route('character.panel') }}" style="color:var(--ink-soft); text-decoration:none;">← Menu personaggi</a></p>
    <h1>Cestino personaggi</h1>
    <p class="subtitle">Personaggi eliminati dal menu — puoi ripristinarli o eliminarli per sempre. Una volta eliminati per sempre, non si possono più recuperare.</p>

    @if($characters->isEmpty())
        <div class="generic-pane">Il cestino è vuoto.</div>
    @else
        <div class="summary-list">
            @foreach($characters as $character)
                <div class="summary-row">
                    <span class="k">{{ $character->name }}</span>
                    <span class="v">
                        <span class="counter">Eliminato il {{ $character->deleted_at->format('d/m/Y') }}</span>
                        <span wire:click="restore({{ $character->id }})" style="cursor:pointer; color:#3FCF8E; margin-left:10px; font-weight:700;">↺ Ripristina</span>
                        <span x-on:click="confirmForceDelete = { id: {{ $character->id }}, name: @js($character->name) }" style="cursor:pointer; color:#F58E4E; margin-left:10px; font-weight:700;">Elimina per sempre</span>
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    <x-confirm-modal
        show="confirmForceDelete !== null"
        title="Eliminare per sempre?"
        confirm-click="$wire.forceDelete(confirmForceDelete.id); confirmForceDelete = null"
        confirm-label="Elimina per sempre"
        :danger="true"
    >
        Stai per eliminare <strong x-text="confirmForceDelete?.name"></strong> in modo <strong>definitivo e irreversibile</strong>: bible, immagini, post e tutto il resto legato a questo personaggio verranno cancellati per sempre. Non potrai più ripristinarlo.
    </x-confirm-modal>
</div>
