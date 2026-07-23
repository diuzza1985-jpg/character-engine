<?php

namespace App\Filament\Resources\SceneElements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SceneElementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('imageAsset.file_path')
                    ->label('Anteprima')
                    ->disk('local'),
                TextColumn::make('tenant.name')
                    ->label('Cliente')
                    ->placeholder('Libreria condivisa')
                    ->sortable(),
                TextColumn::make('category')
                    ->label('Categoria')
                    ->badge(),
                TextColumn::make('key')
                    ->label('Chiave')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
