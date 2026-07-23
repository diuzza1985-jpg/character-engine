<?php

namespace App\Filament\Resources\Characters\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('type')
                    ->label('Tipo')
                    ->helperText('Es: face, full_body, oppure il nome di un\'espressione/attività (es: thinking, smile)')
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
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
