<?php
namespace App\Filament\Resources\ScheduledPosts\Tables;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
class ScheduledPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('character.name')
                    ->label('Personaggio')
                    ->sortable()
                    ->searchable(),
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
                TextColumn::make('scheduled_at')
                    ->label('Programmato per')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'info',
                        'generated' => 'warning',
                        'published' => 'success',
                        'failed' => 'danger',
                        'cancelled' => 'gray',
                        'skipped' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('error')
                    ->label('Errore')
                    ->limit(40)
                    ->tooltip(fn ($record) => $record->error)
                    ->placeholder('—')
                    ->color('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('scheduled_at')
            ->filters([
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'pending' => 'In attesa',
                        'processing' => 'In elaborazione',
                        'generated' => 'Generato',
                        'published' => 'Pubblicato',
                        'failed' => 'Fallito',
                        'cancelled' => 'Annullato',
                        'skipped' => 'Saltato (non pubblicare)',
                    ]),
                SelectFilter::make('character_id')
                    ->label('Personaggio')
                    ->relationship('character', 'name'),
                SelectFilter::make('post_type')
                    ->label('Tipo')
                    ->options([
                        'random' => 'Casuale',
                        'image' => 'Immagine',
                        'carousel' => 'Carosello',
                        'story' => 'Story',
                    ]),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('Annulla')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Annullare questa pubblicazione programmata?')
                    ->visible(fn ($record) => $record->status === 'pending')
                    ->action(fn ($record) => $record->update(['status' => 'cancelled'])),
                EditAction::make()
                    ->visible(fn ($record) => $record->status === 'pending'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
