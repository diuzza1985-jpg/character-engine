<?php

namespace App\Filament\Resources\Characters\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EditorialSettingsRelationManager extends RelationManager
{
    protected static string $relationship = 'editorialSettings';

    protected static ?string $title = 'Impostazioni editoriali';

    protected static ?string $modelLabel = 'impostazioni editoriali';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('max_giorni_silenzio')
                    ->label('Giorni massimi di silenzio')
                    ->helperText('Superata questa soglia senza pubblicare, il cervello editoriale non può più decidere "non_pubblicare".')
                    ->numeric()
                    ->minValue(0)
                    ->default(2)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('max_giorni_silenzio')
            ->columns([
                TextColumn::make('max_giorni_silenzio')
                    ->label('Giorni massimi di silenzio'),
                TextColumn::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()->editorialSettings === null),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                //
            ]);
    }
}
