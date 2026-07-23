<?php

namespace App\Filament\Resources\Plans\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('price_monthly')
                    ->label('Prezzo mensile')
                    ->money('eur')
                    ->sortable(),
                TextColumn::make('credits_included')
                    ->label('Crediti inclusi')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_characters')
                    ->label('Personaggi max')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_generations_per_day')
                    ->label('Generazioni max/giorno')
                    ->numeric()
                    ->sortable(),
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