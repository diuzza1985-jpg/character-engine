<?php
namespace App\Filament\Resources\WeeklyScheduleSlots\Tables;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
class WeeklyScheduleSlotsTable
{
    private const DAYS = [
        0 => 'Domenica', 1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì',
        4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato',
    ];
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('character.name')
                    ->label('Personaggio')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('day_of_week')
                    ->label('Giorno')
                    ->formatStateUsing(fn (int $state) => self::DAYS[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('time_of_day')
                    ->label('Ora')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('post_type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('mode')
                    ->label('Argomento')
                    ->badge()
                    ->color(fn (string $state) => $state === 'manual' ? 'info' : 'gray'),
                TextColumn::make('lifeEvent.title')
                    ->label('Momento')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('instructions')
                    ->label('Istruzioni')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->instructions)
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('enabled')
                    ->label('Abilitato')
                    ->boolean(),
            ])
            ->defaultSort('day_of_week')
            ->filters([
                SelectFilter::make('character_id')
                    ->label('Personaggio')
                    ->relationship('character', 'name'),
                SelectFilter::make('enabled')
                    ->label('Stato')
                    ->options([1 => 'Abilitato', 0 => 'Disabilitato']),
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
