<?php

namespace App\Filament\Resources\Characters\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SceneElementsRelationManager extends RelationManager
{
    protected static string $relationship = 'sceneElements';

    protected static ?string $title = 'Elementi di scena';

    protected static ?string $modelLabel = 'elemento';

    protected static ?string $pluralModelLabel = 'elementi';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                ImageColumn::make('imageAsset.file_path')
                    ->label('Anteprima')
                    ->disk('local'),
                TextColumn::make('category')
                    ->label('Categoria')
                    ->badge(),
                TextColumn::make('key')
                    ->label('Chiave')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('tenant.name')
                    ->label('Cliente')
                    ->placeholder('Libreria condivisa'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelectSearchColumns(['name', 'key'])
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}