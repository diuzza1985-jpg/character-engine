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

        @if($draft)
            @php
                $commLabel = fn (?int $v, string $low, string $high) => match(true) {
                    $v === null => '—',
                    $v <= 30 => $low,
                    $v >= 71 => $high,
                    default => 'Equilibrato',
                };
                $humorLabels = ['mai' => 'Mai — personaggio serio', 'raramente' => 'Raramente', 'leggero' => 'Qualche tocco leggero', 'forte' => 'Tratto forte'];
            @endphp

            <div class="summary-list" style="margin-top:22px;">
                <div class="summary-row"><span class="k">Obiettivo</span><span class="v">{{ $draft->goal ?: '—' }}{{ $draft->goal_secondary ? ' / ' . $draft->goal_secondary : '' }}</span></div>
                <div class="summary-row"><span class="k">Pubblico</span><span class="v">{{ $draft->target_audience ? implode(', ', $draft->target_audience) : '—' }}</span></div>
                <div class="summary-row"><span class="k">Nicchia</span><span class="v">{{ $draft->niche ? implode(', ', $draft->niche) : '—' }}</span></div>
                <div class="summary-row"><span class="k">Ruolo</span><span class="v">{{ $draft->role ?: '—' }}</span></div>
                <div class="summary-row"><span class="k">Con chi vive</span><span class="v">{{ $draft->living_situation ?: '—' }}, {{ $draft->environment ?: '—' }}</span></div>
                <div class="summary-row"><span class="k">Animali</span><span class="v">{{ $draft->pets ? implode(', ', $draft->pets) : '—' }}</span></div>
                <div class="summary-row"><span class="k">Temperamento</span><span class="v">{{ $draft->traits ? implode(', ', $draft->traits) : '—' }}</span></div>
                <div class="summary-row"><span class="k">Valori</span><span class="v">{{ $draft->core_values ? implode(', ', $draft->core_values) : '—' }}</span></div>
                <div class="summary-row"><span class="k">Antipatie</span><span class="v">{{ $draft->dislikes ? implode(', ', $draft->dislikes) : '—' }}</span></div>
                <div class="summary-row"><span class="k">Come comunica</span><span class="v">{{ $commLabel($draft->communication_formality, 'Formale', 'Informale') }}, {{ $commLabel($draft->communication_verbosity, 'Conciso', 'Espansivo') }}, {{ $commLabel($draft->communication_directness, 'Diretto', 'Diplomatico') }}</span></div>
                <div class="summary-row"><span class="k">Emoji</span><span class="v">{{ ucfirst($draft->emoji_usage ?: '—') }}</span></div>
                <div class="summary-row"><span class="k">Umorismo</span><span class="v">{{ $humorLabels[$draft->humor_level] ?? '—' }}</span></div>
                @if($draft->humor_level && $draft->humor_level !== 'mai')
                    <div class="summary-row"><span class="k">Bersagli comici</span><span class="v">{{ $draft->joke_targets ? implode(', ', $draft->joke_targets) : '—' }}</span></div>
                @endif
                <div class="summary-row"><span class="k">Età / presentazione</span><span class="v">{{ $draft->age_range ?: '—' }}, {{ $draft->presentation ?: '—' }}</span></div>
                <div class="summary-row"><span class="k">Stile</span><span class="v">{{ $draft->style_archetype ?: '—' }}</span></div>
                <div class="summary-row"><span class="k">Capelli / occhi</span><span class="v">{{ $draft->hair_color ?: '—' }} {{ $draft->hair_length }} {{ $draft->hair_style }}, occhi {{ $draft->eye_color ?: '—' }}</span></div>
                <div class="summary-row"><span class="k">Corporatura</span><span class="v">{{ $draft->body_type ?: '—' }}</span></div>
                @if($draft->distinguishing_detail)
                    <div class="summary-row"><span class="k">Dettaglio riconoscibile</span><span class="v">{{ $draft->distinguishing_detail }}</span></div>
                @endif
            </div>

            @if($draft->backstory || $draft->life_goals || !empty($draft->fears) || !empty($draft->hobbies) || !empty($draft->dietary_habits) || !empty($draft->typical_phrases) || !empty($draft->hyper_specific_details) || !empty($draft->key_relationships))
                <div class="field-label" style="margin-top:22px;">Approfondimento</div>
                <div class="summary-list">
                    @if($draft->backstory)
                        <div class="summary-row"><span class="k">Storia personale</span><span class="v">{{ $draft->backstory }}</span></div>
                    @endif
                    @if($draft->life_goals)
                        <div class="summary-row"><span class="k">Sogni e obiettivi</span><span class="v">{{ $draft->life_goals }}</span></div>
                    @endif
                    @if(!empty($draft->fears))
                        <div class="summary-row"><span class="k">Paure</span><span class="v">{{ implode(', ', $draft->fears) }}</span></div>
                    @endif
                    @if(!empty($draft->hobbies))
                        <div class="summary-row"><span class="k">Hobby</span><span class="v">{{ implode(', ', $draft->hobbies) }}</span></div>
                    @endif
                    @if(!empty($draft->dietary_habits))
                        <div class="summary-row"><span class="k">Abitudini alimentari</span><span class="v">{{ implode(', ', $draft->dietary_habits) }}</span></div>
                    @endif
                    @if(!empty($draft->typical_phrases))
                        <div class="summary-row"><span class="k">Frasi tipiche</span><span class="v">{{ implode(' · ', $draft->typical_phrases) }}</span></div>
                    @endif
                    @if(!empty($draft->hyper_specific_details))
                        <div class="summary-row"><span class="k">Dettagli iper-specifici</span><span class="v">{{ implode(', ', $draft->hyper_specific_details) }}</span></div>
                    @endif
                    @if(!empty($draft->key_relationships))
                        <div class="summary-row"><span class="k">Relazioni chiave</span><span class="v">{{ collect($draft->key_relationships)->map(fn($r) => ($r['nome'] ?? '') . ($r['relazione'] ?? '' ? ' (' . $r['relazione'] . ')' : ''))->implode(', ') }}</span></div>
                    @endif
                </div>
            @endif
        @else
            <div class="generic-pane" style="margin-top:22px;">Nessun dato del questionario disponibile — questo personaggio è stato creato fuori dal questionario.</div>
        @endif
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
