<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <div class="brand-name">CHARACTER <span class="e">ENGINE</span></div>
            <div class="brand-tag">AI content · personaggi coerenti</div>
        </div>

        <div class="avatar-wrap">
            <svg class="avatar-blob" viewBox="0 0 200 200">
                <defs>
                    <linearGradient id="avatarGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stop-color="#B39BFF"/>
                        <stop offset="100%" stop-color="#6C9BFF"/>
                    </linearGradient>
                </defs>
                <path fill="url(#avatarGrad)" d="M45,-58C60,-49,75,-38,80,-23C85,-8,80,11,71,27C62,43,49,55,33,63C17,71,-1,75,-19,71C-37,67,-53,55,-63,39C-73,23,-77,3,-73,-15C-69,-33,-57,-49,-42,-58C-27,-67,-9,-69,7,-70C23,-71,30,-67,45,-58Z" transform="translate(100 100)"/>
                <g transform="translate(100 96)">
                    <circle cx="-22" cy="-4" r="7" fill="#0B0E1C"/>
                    <circle cx="22" cy="-4" r="7" fill="#0B0E1C"/>
                    <path d="M-16,20 Q0,32 16,20" stroke="#0B0E1C" stroke-width="5" fill="none" stroke-linecap="round"/>
                </g>
            </svg>
        </div>

        <h2>{{ ['intro'=>'Ciao! Costruiamo il tuo personaggio','why'=>'Sto prendendo forma...','identity'=>'Quasi pronto per un nome','personality'=>'Carattere in costruzione','voice'=>'Sto trovando la mia voce','humor'=>'Un pizzico di personalità in più','appearance'=>'Quasi finito','summary'=>'Eccomi, sono definito!'][$step] }}</h2>
        <p class="flavor">{{ ['intro'=>'Rispondi a poche domande veloci: mi trasformerò passo dopo passo in un personaggio vero, con una voce tutta sua.','why'=>'Obiettivo, pubblico e argomenti: sono le fondamenta di tutto il resto.','humor'=>'Anche "mai scherzare" è una scelta di carattere, non una casella vuota.','summary'=>'Non generato ancora — solo pronto, quando vorrai.'][$step] ?? 'Ogni risposta rende il personaggio un po\' più definito.' }}</p>

        @if($step !== 'intro')
            <div class="progress-list">
                @foreach(array_slice($steps, 1) as $i => $s)
                    @php($labels = ['why'=>'Perché esiste','identity'=>'Identità','personality'=>'Personalità','voice'=>'Come comunica','humor'=>'Umorismo','appearance'=>'Aspetto','summary'=>'Riepilogo'])
                    @php($idx = $i + 1)
                    <div class="progress-item @if($idx < $stepIndex) done @elseif($idx === $stepIndex) active @endif">
                        <span class="progress-dot">{{ $idx < $stepIndex ? '✓' : $idx }}</span> {{ $labels[$s] }}
                    </div>
                @endforeach
            </div>
        @endif
    </aside>

    <main>
    <div class="content">

        {{-- SCREEN: intro --}}
        @if($step === 'intro')
        <div class="screen visible">
            <div class="card">
                <div class="intro-illustration"><span>gratuito, nessuna carta richiesta ✨</span></div>
                <h1>Creiamo insieme il tuo personaggio</h1>
                <p class="subtitle">Circa 10-15 minuti, quasi tutto a scelte rapide — quasi nulla da scrivere. Questa parte serve solo a definirlo: potrai generarlo davvero solo quando vorrai.</p>
                <div class="actions">
                    <span></span>
                    <button class="btn-primary" wire:click="$set('step', 'why')">Iniziamo →</button>
                </div>
            </div>
        </div>
        @endif

        {{-- SCREEN: why --}}
        @if($step === 'why')
        <div class="screen visible" x-data="{
            goal: @js($goal),
            goalSecondary: @js($goalSecondary),
            targetAudience: @js($targetAudience),
            niche: @js($niche),
            get canProceed() { return this.goal && this.niche.length >= 2 },
        }">
            <div class="card">
                <p class="eyebrow">Step 1 di 7</p>
                <h1>Perché esiste questo personaggio?</h1>
                <p class="subtitle">È la domanda che conta di più: influenza il tono, gli argomenti e le scelte di tutto il resto del percorso.</p>

                <x-wizard.field-label hint="Scegline uno solo — troppi scopi insieme rischiano di renderlo generico.">Qual è il suo obiettivo principale?</x-wizard.field-label>
                <x-wizard.tile-grid model="goal" :items="[
                    ['value'=>'Promuovere un\'azienda','label'=>'Promuovere un\'azienda','icon'=>'🏢','color'=>'#3A2E5C'],
                    ['value'=>'Diventare un influencer','label'=>'Diventare un influencer','icon'=>'🌟','color'=>'#5C2E4A'],
                    ['value'=>'Intrattenere','label'=>'Intrattenere','icon'=>'🎭','color'=>'#2E3E5C'],
                    ['value'=>'Divulgare','label'=>'Divulgare','icon'=>'📚','color'=>'#2E5C46'],
                    ['value'=>'Educare','label'=>'Educare','icon'=>'🎓','color'=>'#5C4E2E'],
                    ['value'=>'Vendere prodotti o servizi','label'=>'Vendere prodotti','icon'=>'🛍️','color'=>'#2E4A5C'],
                    ['value'=>'Raccontare una professione','label'=>'Una professione','icon'=>'💼','color'=>'#4A2E5C'],
                    ['value'=>'Fare storytelling','label'=>'Storytelling','icon'=>'📖','color'=>'#5C3E2E'],
                ]" />

                <x-wizard.field-label hint="Facoltativo, se serve una sfumatura in più.">Obiettivo secondario</x-wizard.field-label>
                <x-wizard.text-input model="goalSecondary" placeholder="Es. anche fare storytelling" />

                <x-wizard.field-label counter-expr="'facoltativo'">Con chi parla soprattutto?</x-wizard.field-label>
                <x-wizard.chip-row model="targetAudience" :items="[
                    ['value'=>'Imprenditori','label'=>'Imprenditori'],['value'=>'Studenti','label'=>'Studenti'],
                    ['value'=>'Mamme e famiglie','label'=>'Mamme e famiglie'],['value'=>'Aziende (B2B)','label'=>'Aziende (B2B)'],
                    ['value'=>'Sviluppatori','label'=>'Sviluppatori'],['value'=>'Over 60','label'=>'Over 60'],
                    ['value'=>'Giovani / Gen Z','label'=>'Giovani / Gen Z'],['value'=>'Pubblico generalista','label'=>'Pubblico generalista'],
                ]" />

                <x-wizard.field-label hint="Obbligatorio — orienta anche quali notizie reali il personaggio potrà commentare." counter-expr="niche.length + '/2 minimo'" counter-ok-expr="niche.length >= 2">Di cosa parla normalmente?</x-wizard.field-label>
                <x-wizard.chip-row model="niche" :items="[
                    ['value'=>'Tecnologia','label'=>'Tecnologia'],['value'=>'Cucina','label'=>'Cucina'],['value'=>'Viaggi','label'=>'Viaggi'],
                    ['value'=>'Fitness','label'=>'Fitness'],['value'=>'Animali','label'=>'Animali'],['value'=>'Marketing','label'=>'Marketing'],
                    ['value'=>'Finanza','label'=>'Finanza'],['value'=>'Psicologia','label'=>'Psicologia'],['value'=>'Diritto','label'=>'Diritto'],
                    ['value'=>'Videogiochi','label'=>'Videogiochi'],['value'=>'Moda','label'=>'Moda'],['value'=>'Casa e famiglia','label'=>'Casa e famiglia'],
                    ['value'=>'Sport','label'=>'Sport'],['value'=>'Salute','label'=>'Salute'],
                ]" />

                <div class="actions">
                    <button class="btn-ghost" wire:click="$set('step', 'intro')">← Indietro</button>
                    <button class="btn-primary" :disabled="!canProceed" x-on:click="$wire.nextStep('why', 'identity', { goal, goalSecondary, targetAudience, niche })">Avanti →</button>
                </div>
            </div>
        </div>
        @endif

        {{-- SCREEN: identity --}}
        @if($step === 'identity')
        <div class="screen visible" x-data="{
            name: @js($name), role: @js($role), oneLiner: @js($oneLiner),
            livingSituation: @js($livingSituation), pets: @js($pets), environment: @js($environment),
            get canProceed() { return this.name && this.oneLiner },
        }">
            <div class="card">
                <p class="eyebrow">Step 2 di 7</p>
                <h1>Chi è questo personaggio?</h1>
                <p class="subtitle">L'identità di base: come si chiama e come si presenterebbe in una frase.</p>

                <x-wizard.field-label>Nome del personaggio</x-wizard.field-label>
                <x-wizard.text-input model="name" placeholder="Es. Sofia" :maxlength="255" />

                <x-wizard.field-label hint="Scelta da lista o testo libero se non trovi quella giusta.">Ruolo / professione</x-wizard.field-label>
                <x-wizard.chip-row model="role" :multi="false" :items="[
                    ['value'=>'Sviluppatrice','label'=>'Sviluppatrice'],['value'=>'Insegnante','label'=>'Insegnante'],
                    ['value'=>'Medico','label'=>'Medico'],['value'=>'Artigiano','label'=>'Artigiano'],
                    ['value'=>'Libero professionista','label'=>'Libero professionista'],['value'=>'Studentessa','label'=>'Studentessa'],
                    ['value'=>'Imprenditrice','label'=>'Imprenditrice'],
                ]" />
                <x-wizard.text-input model="role" placeholder="Oppure scrivi tu il ruolo" :maxlength="255" />

                <x-wizard.field-label hint="Max ~120 caratteri, es. &quot;Sviluppatrice che vive di caffè e debug notturni&quot;.">In una frase, chi è</x-wizard.field-label>
                <x-wizard.text-input model="oneLiner" placeholder="Es. Sviluppatrice che vive di caffè e debug notturni" :maxlength="120" />
                <div class="helper-suggest" x-on:click="oneLiner = ['Vive tra caffè, scadenze e piccole vittorie quotidiane','Prende tutto sul serio tranne sé stesso/a','Ha sempre una battuta pronta e un piano di riserva'][Math.floor(Math.random()*3)]">✨ Suggeriscimi tu</div>

                <x-wizard.field-label>Con chi vive</x-wizard.field-label>
                <x-wizard.chip-row model="livingSituation" :multi="false" :items="[
                    ['value'=>'Da solo/a','label'=>'Da solo/a'],['value'=>'Famiglia','label'=>'Famiglia'],
                    ['value'=>'Partner','label'=>'Partner'],['value'=>'Coinquilini','label'=>'Coinquilini'],
                ]" />

                <x-wizard.field-label counter-expr="'facoltativo'">Animali domestici</x-wizard.field-label>
                <x-wizard.chip-row model="pets" :items="[
                    ['value'=>'Cane','label'=>'Cane'],['value'=>'Gatto','label'=>'Gatto'],['value'=>'Altro','label'=>'Altro'],
                ]" />

                <x-wizard.field-label>Ambiente</x-wizard.field-label>
                <x-wizard.chip-row model="environment" :multi="false" :items="[
                    ['value'=>'Città','label'=>'Città'],['value'=>'Paese','label'=>'Paese'],
                    ['value'=>'Campagna','label'=>'Campagna'],['value'=>'Mare','label'=>'Mare'],['value'=>'Montagna','label'=>'Montagna'],
                ]" />

                <div class="actions">
                    <button class="btn-ghost" wire:click="$set('step', 'why')">← Indietro</button>
                    <button class="btn-primary" :disabled="!canProceed" x-on:click="$wire.nextStep('identity', 'personality', { name, role, oneLiner, livingSituation, pets, environment })">Avanti →</button>
                </div>
            </div>
        </div>
        @endif

        {{-- SCREEN: personality --}}
        @if($step === 'personality')
        <div class="screen visible" x-data="{
            traits: @js($traits), coreValues: @js($coreValues), dislikes: @js($dislikes),
            get canProceed() { return this.traits.length >= 4 && this.coreValues.length >= 2 && this.dislikes.length >= 2 },
        }">
            <div class="card">
                <p class="eyebrow">Step 3 di 7</p>
                <h1>Che carattere ha?</h1>
                <p class="subtitle">Tratti, valori e antipatie: quello che lo rende riconoscibile nel modo di reagire alle cose.</p>

                <x-wizard.field-label hint="Scegli 4-6 tratti che lo/la descrivono meglio." counter-expr="traits.length + '/4 minimo'" counter-ok-expr="traits.length >= 4">Temperamento</x-wizard.field-label>
                <x-wizard.chip-row model="traits" :items="[
                    ['value'=>'Estroverso','label'=>'Estroverso'],['value'=>'Riflessivo','label'=>'Riflessivo'],['value'=>'Testardo','label'=>'Testardo'],
                    ['value'=>'Empatico','label'=>'Empatico'],['value'=>'Pragmatico','label'=>'Pragmatico'],['value'=>'Sognatore','label'=>'Sognatore'],
                    ['value'=>'Ansioso','label'=>'Ansioso'],['value'=>'Calmo','label'=>'Calmo'],['value'=>'Curioso','label'=>'Curioso'],
                    ['value'=>'Disciplinato','label'=>'Disciplinato'],['value'=>'Spontaneo','label'=>'Spontaneo'],['value'=>'Prudente','label'=>'Prudente'],
                    ['value'=>'Ottimista','label'=>'Ottimista'],['value'=>'Scettico','label'=>'Scettico'],['value'=>'Generoso','label'=>'Generoso'],['value'=>'Riservato','label'=>'Riservato'],
                ]" />

                <x-wizard.field-label hint="Scegli 2-3 cose importanti per lui/lei." counter-expr="coreValues.length + '/2 minimo'" counter-ok-expr="coreValues.length >= 2">Valori</x-wizard.field-label>
                <x-wizard.chip-row model="coreValues" :items="[
                    ['value'=>'Famiglia','label'=>'Famiglia'],['value'=>'Libertà','label'=>'Libertà'],['value'=>'Ordine','label'=>'Ordine'],
                    ['value'=>'Creatività','label'=>'Creatività'],['value'=>'Giustizia','label'=>'Giustizia'],['value'=>'Crescita personale','label'=>'Crescita personale'],
                    ['value'=>'Sicurezza economica','label'=>'Sicurezza economica'],['value'=>'Avventura','label'=>'Avventura'],
                    ['value'=>'Spiritualità','label'=>'Spiritualità'],['value'=>'Lealtà','label'=>'Lealtà'],['value'=>'Indipendenza','label'=>'Indipendenza'],
                ]" />

                <x-wizard.field-label hint="Scegli 2-3 cose che proprio non sopporta." counter-expr="dislikes.length + '/2 minimo'" counter-ok-expr="dislikes.length >= 2">Antipatie</x-wizard.field-label>
                <x-wizard.chip-row model="dislikes" :items="[
                    ['value'=>'Ingiustizia','label'=>'Ingiustizia'],['value'=>'Disordine','label'=>'Disordine'],['value'=>'Maleducazione','label'=>'Maleducazione'],
                    ['value'=>'Ritardi','label'=>'Ritardi'],['value'=>'Ipocrisia','label'=>'Ipocrisia'],['value'=>'Rumore','label'=>'Rumore'],
                    ['value'=>'Superficialità','label'=>'Superficialità'],['value'=>'Sprechi','label'=>'Sprechi'],['value'=>'Pressione sociale','label'=>'Pressione sociale'],
                ]" />

                <div class="actions">
                    <button class="btn-ghost" wire:click="$set('step', 'identity')">← Indietro</button>
                    <button class="btn-primary" :disabled="!canProceed" x-on:click="$wire.nextStep('personality', 'voice', { traits, coreValues, dislikes })">Avanti →</button>
                </div>
            </div>
        </div>
        @endif

        {{-- SCREEN: voice --}}
        @if($step === 'voice')
        <div class="screen visible" x-data="{
            communicationFormality: @js($communicationFormality),
            communicationVerbosity: @js($communicationVerbosity),
            communicationDirectness: @js($communicationDirectness),
            emojiUsage: @js($emojiUsage),
            get canProceed() { return !!this.emojiUsage },
        }">
            <div class="card">
                <p class="eyebrow">Step 4 di 7</p>
                <h1>Come comunica?</h1>
                <p class="subtitle">Il tono con cui scrive ogni contenuto — non serve scrivere nulla, solo tarare qualche cursore.</p>

                <x-wizard.slider model="communicationFormality" left-label="Formale" right-label="Informale" />
                <x-wizard.slider model="communicationVerbosity" left-label="Conciso" right-label="Espansivo" />
                <x-wizard.slider model="communicationDirectness" left-label="Diretto" right-label="Diplomatico" />

                <x-wizard.field-label>Usa emoji</x-wizard.field-label>
                <x-wizard.chip-row model="emojiUsage" :multi="false" :items="[
                    ['value'=>'mai','label'=>'Mai'],['value'=>'raramente','label'=>'Raramente'],['value'=>'spesso','label'=>'Spesso'],
                ]" />

                <div class="actions">
                    <button class="btn-ghost" wire:click="$set('step', 'personality')">← Indietro</button>
                    <button class="btn-primary" :disabled="!canProceed" x-on:click="$wire.nextStep('voice', 'humor', { communicationFormality, communicationVerbosity, communicationDirectness, emojiUsage })">Avanti →</button>
                </div>
            </div>
        </div>
        @endif

        {{-- SCREEN: humor --}}
        @if($step === 'humor')
        <div class="screen visible" x-data="{
            humorLevel: @js($humorLevel), jokeTargets: @js($jokeTargets), humorSafeTopics: @js($humorSafeTopics),
            get isSerious() { return this.humorLevel === 'mai' },
            get canProceed() { return this.humorLevel && (this.isSerious || this.jokeTargets.length >= 5) },
        }">
            <div class="card">
                <p class="eyebrow">Step 5 di 7</p>
                <h1>Quanto è presente l'ironia?</h1>
                <p class="subtitle">Nessun problema se la risposta è "mai" — non tutti i personaggi devono scherzare, e il resto del percorso si adatta di conseguenza.</p>

                <x-wizard.tile-grid model="humorLevel" :wide="['mai','raramente','leggero','forte']" :items="[
                    ['value'=>'mai','label'=>'Mai — è un personaggio serio','icon'=>'😐','color'=>'#2E2E4A'],
                    ['value'=>'raramente','label'=>'Raramente, solo in occasioni particolari','icon'=>'🙂','color'=>'#5C4E2E'],
                    ['value'=>'leggero','label'=>'Qualche tocco leggero','icon'=>'😄','color'=>'#5C2E4A'],
                    ['value'=>'forte','label'=>'È uno dei suoi tratti forti','icon'=>'😂','color'=>'#5C3E2E'],
                ]" />

                <div class="serious-note" x-show="isSerious" style="display:none;">
                    <span>👍</span>
                    <div>Registrato: questo personaggio <strong>non userà mai umorismo</strong>. Non dovrai definire battute o bersagli comici — passiamo allo step successivo.</div>
                </div>

                <div class="branch-reveal" :class="{ open: humorLevel && !isSerious }">
                    <x-wizard.field-label hint="Scegli almeno 5 spunti." counter-expr="jokeTargets.length + '/5 minimo'" counter-ok-expr="jokeTargets.length >= 5">Di cosa scherza spesso?</x-wizard.field-label>
                    <x-wizard.chip-row model="jokeTargets" :items="[
                        ['value'=>'Lavoro','label'=>'Lavoro'],['value'=>'Famiglia','label'=>'Famiglia'],['value'=>'Tecnologia','label'=>'Tecnologia'],
                        ['value'=>'Animali domestici','label'=>'Animali domestici'],['value'=>'Burocrazia','label'=>'Burocrazia'],['value'=>'Cibo','label'=>'Cibo'],
                        ['value'=>'Appuntamenti mancati','label'=>'Appuntamenti mancati'],['value'=>'Vita di coppia','label'=>'Vita di coppia'],
                        ['value'=>'Traffico','label'=>'Traffico'],['value'=>'Diete fallite','label'=>'Diete fallite'],
                    ]" />

                    <x-wizard.field-label hint="Precompilato con i limiti di sicurezza di base — puoi solo aggiungerne altri.">Cose su cui non scherza mai</x-wizard.field-label>
                    <x-wizard.chip-row model="humorSafeTopics" :allow-custom="true" :default-locked="['Aspetto fisico','Salute / malattie','Salute mentale','Difficoltà economiche','Lutti e tragedie']" :items="[
                        ['value'=>'Aspetto fisico','label'=>'Aspetto fisico'],['value'=>'Salute / malattie','label'=>'Salute / malattie'],
                        ['value'=>'Salute mentale','label'=>'Salute mentale'],['value'=>'Difficoltà economiche','label'=>'Difficoltà economiche'],
                        ['value'=>'Lutti e tragedie','label'=>'Lutti e tragedie'],
                    ]" />
                </div>

                <div class="actions">
                    <button class="btn-ghost" wire:click="$set('step', 'voice')">← Indietro</button>
                    <button class="btn-primary" :disabled="!canProceed" x-on:click="$wire.nextStep('humor', 'appearance', { humorLevel, jokeTargets, humorSafeTopics })">Avanti →</button>
                </div>
            </div>
        </div>
        @endif

        {{-- SCREEN: appearance --}}
        @if($step === 'appearance')
        <div class="screen visible" x-data="{
            ageRange: @js($ageRange), presentation: @js($presentation), styleArchetype: @js($styleArchetype),
            hairColor: @js($hairColor), hairStyle: @js($hairStyle), eyeColor: @js($eyeColor), bodyType: @js($bodyType),
            noseDetail: @js($noseDetail), mouthDetail: @js($mouthDetail), distinguishingDetail: @js($distinguishingDetail),
            showAdvanced: false,
            get canProceed() { return this.ageRange && this.presentation && this.styleArchetype },
        }">
            <div class="card">
                <p class="eyebrow">Step 6 di 7</p>
                <h1>Che aspetto ha?</h1>
                <p class="subtitle">Definiamo solo le caratteristiche: l'immagine vera la genererai quando vorrai, dopo la registrazione.</p>

                <x-wizard.field-label>Fascia d'età</x-wizard.field-label>
                <x-wizard.chip-row model="ageRange" :multi="false" :items="[['value'=>'18-25','label'=>'18-25'],['value'=>'26-35','label'=>'26-35'],['value'=>'36-50','label'=>'36-50'],['value'=>'50+','label'=>'50+']]" />

                <x-wizard.field-label>Presentazione</x-wizard.field-label>
                <x-wizard.chip-row model="presentation" :multi="false" :items="[['value'=>'Femminile','label'=>'Femminile'],['value'=>'Maschile','label'=>'Maschile'],['value'=>'Non binaria','label'=>'Non binaria'],['value'=>'Altro','label'=>'Altro']]" />

                <x-wizard.field-label>Stile</x-wizard.field-label>
                <x-wizard.tile-grid model="styleArchetype" :items="[
                    ['value'=>'Casual sportivo','label'=>'Casual sportivo','icon'=>'🏃','color'=>'#2E5C46'],
                    ['value'=>'Elegante minimal','label'=>'Elegante minimal','icon'=>'🖤','color'=>'#3A2E5C'],
                    ['value'=>'Boho','label'=>'Boho','icon'=>'🌾','color'=>'#5C4E2E'],
                    ['value'=>'Streetwear','label'=>'Streetwear','icon'=>'🧢','color'=>'#2E4A5C'],
                    ['value'=>'Classico professionale','label'=>'Classico','icon'=>'👔','color'=>'#5C3E2E'],
                    ['value'=>'Artistico eccentrico','label'=>'Artistico','icon'=>'🎨','color'=>'#4A2E5C'],
                ]" />

                <x-wizard.field-label>Colore capelli</x-wizard.field-label>
                <x-wizard.swatch-row model="hairColor" :items="[
                    ['value'=>'Nero','label'=>'Nero','color'=>'#2B2320'],['value'=>'Castano','label'=>'Castano','color'=>'#6B4630'],
                    ['value'=>'Biondo','label'=>'Biondo','color'=>'#E3C185'],['value'=>'Rosso','label'=>'Rosso','color'=>'#B0552F'],
                    ['value'=>'Grigio/argento','label'=>'Grigio/argento','color'=>'#B7B4C2'],
                    ['value'=>'Colorato/fantasia','label'=>'Colorato/fantasia','color'=>'linear-gradient(135deg,#B39BFF,#6C9BFF)'],
                ]" />

                <x-wizard.field-label>Stile capelli</x-wizard.field-label>
                <x-wizard.chip-row model="hairStyle" :multi="false" :items="[['value'=>'Corti','label'=>'Corti'],['value'=>'Medi','label'=>'Medi'],['value'=>'Lunghi','label'=>'Lunghi'],['value'=>'Ricci','label'=>'Ricci'],['value'=>'Raccolti','label'=>'Raccolti'],['value'=>'Rasati/calvo','label'=>'Rasati/calvo']]" />

                <x-wizard.field-label>Colore occhi</x-wizard.field-label>
                <x-wizard.swatch-row model="eyeColor" :items="[
                    ['value'=>'Marroni','label'=>'Marroni','color'=>'#5C3A21'],['value'=>'Verdi','label'=>'Verdi','color'=>'#5C8A54'],
                    ['value'=>'Azzurri','label'=>'Azzurri','color'=>'#6FA8DC'],['value'=>'Grigi','label'=>'Grigi','color'=>'#9AA0A6'],
                    ['value'=>'Nocciola','label'=>'Nocciola','color'=>'#8A6A3D'],
                ]" />

                <x-wizard.field-label>Corporatura</x-wizard.field-label>
                <x-wizard.chip-row model="bodyType" :multi="false" :items="[['value'=>'Snella','label'=>'Snella'],['value'=>'Media','label'=>'Media'],['value'=>'Curvy','label'=>'Curvy'],['value'=>'Atletica','label'=>'Atletica'],['value'=>'Robusta','label'=>'Robusta'],['value'=>'Preferisco non specificare','label'=>'Preferisco non specificare']]" />

                <div class="helper-suggest" style="margin-top:22px;" x-on:click="showAdvanced = !showAdvanced" x-text="showAdvanced ? '－ Nascondi dettagli avanzati' : '＋ Affina i dettagli (naso, bocca) — facoltativo'"></div>
                <div x-show="showAdvanced" style="display:none;">
                    <x-wizard.field-label style="margin-top:20px;">Naso</x-wizard.field-label>
                    <x-wizard.chip-row model="noseDetail" :multi="false" :items="[['value'=>'Regolare','label'=>'Regolare'],['value'=>'Pronunciato','label'=>'Pronunciato'],['value'=>'Piccolo e delicato','label'=>'Piccolo e delicato'],['value'=>'All\'insù','label'=>'All\'insù']]" />
                    <x-wizard.field-label>Bocca</x-wizard.field-label>
                    <x-wizard.chip-row model="mouthDetail" :multi="false" :items="[['value'=>'Regolare','label'=>'Regolare'],['value'=>'Labbra pronunciate','label'=>'Labbra pronunciate'],['value'=>'Labbra sottili','label'=>'Labbra sottili'],['value'=>'Sorriso ampio','label'=>'Sorriso ampio']]" />
                </div>

                <x-wizard.field-label counter-expr="'facoltativo'">Un dettaglio che lo rende riconoscibile</x-wizard.field-label>
                <p class="field-hint">Es. "porta sempre occhiali tondi", "un piccolo tatuaggio sul polso"</p>
                <x-wizard.text-input model="distinguishingDetail" placeholder="Es. porta sempre occhiali tondi" :maxlength="255" />
                <div class="helper-suggest" x-on:click="distinguishingDetail = ['Porta sempre un accessorio dello stesso colore','Ha un piccolo tatuaggio simbolico sul polso','Tiene sempre in tasca un oggetto portafortuna'][Math.floor(Math.random()*3)]">✨ Suggeriscimi tu un dettaglio</div>

                <div class="actions">
                    <button class="btn-ghost" wire:click="$set('step', 'humor')">← Indietro</button>
                    <button class="btn-primary" :disabled="!canProceed" x-on:click="$wire.nextStep('appearance', 'summary', { ageRange, presentation, styleArchetype, hairColor, hairStyle, eyeColor, bodyType, noseDetail, mouthDetail, distinguishingDetail })">Avanti →</button>
                </div>
            </div>
        </div>
        @endif

        {{-- SCREEN: summary --}}
        @if($step === 'summary')
        <div class="screen visible">
            <div class="card">
                <p class="eyebrow">Fine del nucleo</p>
                <h1>Il tuo personaggio è definito</h1>
                <p class="subtitle">Ecco un riepilogo di quello che hai scelto. Nessuna immagine o contenuto è stato ancora generato — questa parte serve solo a definirlo.</p>

                <div class="ghost-avatar">
                    <span style="font-size:26px;">👤</span>
                    <span>prenderà vita quando deciderai di generarlo</span>
                </div>

                <div class="summary-list">
                    <div class="summary-row"><span class="k">Nome</span><span class="v">{{ $name ?: '—' }}</span></div>
                    <div class="summary-row"><span class="k">In una frase</span><span class="v">{{ $oneLiner ?: '—' }}</span></div>
                    <div class="summary-row"><span class="k">Obiettivo</span><span class="v">{{ $goal ?: '—' }}</span></div>
                    <div class="summary-row"><span class="k">Nicchia</span><span class="v">{{ $niche ? implode(', ', $niche) : '—' }}</span></div>
                    <div class="summary-row"><span class="k">Umorismo</span><span class="v">{{ ['mai'=>'Mai — personaggio serio','raramente'=>'Raramente','leggero'=>'Qualche tocco leggero','forte'=>'Tratto forte'][$humorLevel] ?? '—' }}</span></div>
                    <div class="summary-row"><span class="k">Stile visivo</span><span class="v">{{ $styleArchetype ?: '—' }}</span></div>
                    <div class="summary-row"><span class="k">Corporatura</span><span class="v">{{ $bodyType ?: '—' }}</span></div>
                </div>

                <div class="hype-box">
                    <h3>Salva il tuo personaggio</h3>
                    <p>Serve un account per non perderlo: se non hai ancora effettuato l'accesso ti chiederemo di accedere o registrarti al volo, poi potrai arricchirlo e attivarlo quando vuoi.</p>
                    <div class="hype-actions">
                        <button class="btn-primary" wire:click="saveAndContinue">Salva e continua →</button>
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>
    </main>
</div>
