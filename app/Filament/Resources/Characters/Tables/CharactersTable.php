<?php
namespace App\Filament\Resources\Characters\Tables;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
class CharactersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('tenant.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => 'Attivo',
                        'draft' => 'Bozza',
                        'paused' => 'In pausa',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'draft' => 'gray',
                        'paused' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Cancellato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record) => ! $record->trashed()),
                RestoreAction::make()
                    ->requiresConfirmation(),
                ForceDeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Eliminare definitivamente questo personaggio?')
                    ->modalDescription('Questa azione è irreversibile: il personaggio e tutti i dati collegati (bible, life events, cronologia, post, account social) verranno rimossi per sempre dal database.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Spostare nel cestino i personaggi selezionati?')
                        ->modalDescription('Restano recuperabili dal filtro "Cestinati" finché non li elimini definitivamente.'),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Eliminare definitivamente i personaggi selezionati?')
                        ->modalDescription('Questa azione è irreversibile.'),
                ]),
            ]);
    }
}
