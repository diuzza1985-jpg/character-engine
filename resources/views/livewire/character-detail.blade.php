<div class="card" x-data="{ tab: 'riepilogo' }">
    <p class="eyebrow"><a href="{{ route('character.panel') }}" style="color:var(--ink-soft); text-decoration:none;">← Menu personaggi</a></p>
    <h1>{{ $character->name }}</h1>
    <p class="subtitle">{{ $character->one_liner }}</p>

    <div class="tabbar">
        <div class="tab" :class="{ active: tab === 'riepilogo' }" x-on:click="tab = 'riepilogo'">Riepilogo</div>
        <div class="tab" :class="{ active: tab === 'parentele' }" x-on:click="tab = 'parentele'">Parentele</div>
        <div class="tab locked" :class="{ active: tab === 'assets' }" x-on:click="tab = 'assets'">🔒 Assets & Immagini</div>
    </div>

    <div x-show="tab === 'riepilogo'">
        <div class="ig-banner">
            <span>{{ $character->status === 'active' ? '✅' : '⏸️' }}</span>
            <span>
                <strong>{{ $character->status === 'active' ? 'Attivo — pubblica davvero' : 'Salvato — non pubblica' }}</strong>.
                @if($character->status === 'active')
                    Il motore editoriale lo considera per la pubblicazione automatica.
                @else
                    Ha una sua personalità ma il motore non genera né pubblica mai contenuti per lui, finché non lo attivi.
                @endif
            </span>
        </div>

        @error('activation') <p class="field-hint" style="color:#F58E4E;">{{ $message }}</p> @enderror

        <div class="actions" style="margin-top:0;">
            @if($character->draft)
                <a href="{{ route('character.edit', $character) }}" class="btn-outline">Modifica questionario</a>
            @else
                <span class="counter">Creato fuori dal questionario — non modificabile da qui</span>
            @endif
            <button class="btn-primary" wire:click="toggleActive">{{ $character->status === 'active' ? 'Metti in pausa' : 'Attiva' }}</button>
        </div>

        <div class="field-label" style="margin-top:28px;">Sezioni</div>
        @forelse($bibleSections as $section)
            <div class="field-label" style="margin-top:18px; font-size:13px; color:var(--ink-soft); text-transform:uppercase;">{{ ucfirst($section->section_key) }}</div>
            <div class="generic-pane" style="white-space:pre-wrap; text-align:left; color:var(--ink);">{{ $section->content }}</div>
        @empty
            <div class="generic-pane">Nessuna sezione ancora — completa il questionario.</div>
        @endforelse
    </div>

    <div x-show="tab === 'parentele'" style="display:none;">
        @php($allKinships = $kinships->map(fn($k) => ['nome' => $k->relatedCharacter->name, 'tipo' => $k->relationship_type, 'id' => $k->id, 'mine' => true])->concat($kinshipsAsRelated->map(fn($k) => ['nome' => $k->character->name, 'tipo' => $k->inverseLabel(), 'id' => $k->id, 'mine' => false])))
        @if($allKinships->isEmpty())
            <div class="generic-pane">Nessuna parentela ancora collegata.</div>
        @else
            <div class="summary-list">
                @foreach($allKinships as $k)
                    <div class="summary-row">
                        <span class="k">{{ $k['nome'] }}</span>
                        <span class="v">
                            {{ $k['tipo'] }}
                            @if($k['mine'])
                                <span wire:click="removeKinship({{ $k['id'] }})" style="cursor:pointer; color:#F58E4E; margin-left:10px;">✕</span>
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @endif

        @if($otherCharacters->isNotEmpty())
            <div class="field-label" style="margin-top:22px;">Aggiungi una parentela</div>
            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                <select wire:model="newRelatedCharacterId" class="text-input" style="width:180px;">
                    <option value="">Scegli un personaggio</option>
                    @foreach($otherCharacters as $other)
                        <option value="{{ $other->id }}">{{ $other->name }}</option>
                    @endforeach
                </select>
                <select wire:model="newRelationshipType" class="text-input" style="width:160px;">
                    <option value="">Tipo di relazione</option>
                    @foreach($relationshipTypes as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
                <input type="text" class="text-input" style="width:180px;" wire:model="newRelationshipNotes" placeholder="Nota facoltativa">
                <button class="chip" wire:click="addKinship">+ Aggiungi</button>
            </div>
            @error('newRelatedCharacterId') <p class="field-hint" style="color:#F58E4E;">{{ $message }}</p> @enderror
            @error('newRelationshipType') <p class="field-hint" style="color:#F58E4E;">{{ $message }}</p> @enderror
        @else
            <p class="field-hint" style="margin-top:22px;">Crea un altro personaggio per poterlo collegare a questo.</p>
        @endif
    </div>

    <div x-show="tab === 'assets'" style="display:none;">
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
