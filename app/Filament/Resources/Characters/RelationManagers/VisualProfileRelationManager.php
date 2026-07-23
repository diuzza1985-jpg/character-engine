<?php

namespace App\Filament\Resources\Characters\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VisualProfileRelationManager extends RelationManager
{
    protected static string $relationship = 'visualProfile';

    protected static ?string $title = 'Profilo visivo';

    protected static ?string $modelLabel = 'profilo visivo';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('age_description')
                    ->label('Età')
                    ->columnSpanFull(),
                Textarea::make('face_description')
                    ->label('Volto')
                    ->columnSpanFull(),
                Textarea::make('hair_description')
                    ->label('Capelli')
                    ->columnSpanFull(),
                Textarea::make('wardrobe_notes')
                    ->label('Abbigliamento')
                    ->columnSpanFull(),
                Textarea::make('visual_rules_text')
                    ->label('Regole visive')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('age_description')
            ->columns([
                TextColumn::make('face_description')
                    ->label('Volto')
                    ->limit(60),
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
                    ->visible(fn ($livewire) => $livewire->getOwnerRecord()->visualProfile === null),
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
