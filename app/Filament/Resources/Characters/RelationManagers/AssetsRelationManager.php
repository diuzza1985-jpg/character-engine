<?php

namespace App\Filament\Resources\Characters\RelationManagers;

use App\Models\CharacterAsset;
use App\Services\ReferenceImageSelector;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'assets';

    protected static ?string $title = 'Foto di riferimento';

    protected static ?string $modelLabel = 'foto';

    protected static ?string $pluralModelLabel = 'foto';

    private const HAIR_LENGTH_OPTIONS = [
        'Corti' => 'Corti',
        'Medi' => 'Medi',
        'Lunghi' => 'Lunghi',
        'Non visibile' => 'Non visibile (raccolti/coperti)',
    ];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('type')
                    ->label('Tipo')
                    ->helperText('Es: face, full_body, oppure il nome di un\'espressione/attività (es: thinking, smile)')
                    ->live()
                    ->required(),
                TextInput::make('label')
                    ->label('Etichetta')
                    ->helperText('Descrizione libera, facoltativa'),
                FileUpload::make('file_path')
                    ->label('Immagine')
                    ->image()
                    ->disk('local')
                    ->directory(fn ($livewire) => 'characters/'
                        .$livewire->getOwnerRecord()->tenant_id
                        .'/'.$livewire->getOwnerRecord()->id)
                    ->required(),
                Toggle::make('is_default')
                    ->label('Immagine predefinita'),
                // Obbligatorio solo per i type che raffigurano il personaggio (i ROTATION_TYPES
                // usati da ReferenceImageSelector per la generazione) — non serve per asset
                // non-persona come companion_*/oggetti. Guardia minima trovata mancante indagando
                // l'incoerenza capelli di Sofia: nessun vincolo impediva di caricare più foto
                // reference con lunghezze diverse senza accorgersene.
                Select::make('hair_length_shown')
                    ->label('Lunghezza capelli mostrata in questa foto')
                    ->helperText('Usata solo per avvisare (non blocca) se diverge dalla lunghezza canonica del personaggio.')
                    ->options(self::HAIR_LENGTH_OPTIONS)
                    ->visible(fn (callable $get) => in_array($get('type'), ReferenceImageSelector::ROTATION_TYPES, true))
                    ->required(fn (callable $get) => in_array($get('type'), ReferenceImageSelector::ROTATION_TYPES, true)),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                ImageColumn::make('file_path')
                    ->label('Anteprima')
                    ->disk('local'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable(),
                TextColumn::make('label')
                    ->label('Etichetta')
                    ->searchable(),
                IconColumn::make('is_default')
                    ->label('Predefinita')
                    ->boolean(),
                TextColumn::make('hair_length_shown')
                    ->label('Lunghezza mostrata')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->after(fn (CharacterAsset $record) => $this->warnIfHairLengthMismatch($record)),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn (CharacterAsset $record) => $this->warnIfHairLengthMismatch($record)),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Avviso non bloccante (il salvataggio è già avvenuto, è comunque una decisione umana):
     * confronta la lunghezza dichiarata su questa foto con la hair_length canonica del
     * personaggio (dal questionario convertito) e segnala solo se diverge. Nessun confronto
     * possibile per personaggi legacy senza draft collegata (es. Sofia) — silenzioso in quel caso,
     * non c'è un valore canonico strutturato con cui confrontare.
     */
    private function warnIfHairLengthMismatch(CharacterAsset $record): void
    {
        if (! $record->hair_length_shown || $record->hair_length_shown === 'Non visibile') {
            return;
        }

        $canonical = $record->character?->draft?->hair_length;
        if (! $canonical || $canonical === $record->hair_length_shown) {
            return;
        }

        Notification::make()
            ->title('Lunghezza capelli non coerente')
            ->body("Questa foto mostra capelli \"{$record->hair_length_shown}\", ma il personaggio ha lunghezza canonica \"{$canonical}\" — verifica se è voluto prima di usarla come reference.")
            ->warning()
            ->send();
    }
}
