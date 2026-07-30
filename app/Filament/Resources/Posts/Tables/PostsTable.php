<?php

namespace App\Filament\Resources\Posts\Tables;

use App\Jobs\PublishInstagramPostJob;
use App\Models\Post;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class PostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview')
                    ->label('')
                    ->disk('local')
                    ->getStateUsing(fn (Post $record) => $record->media_urls[0] ?? null)
                    ->square(),
                TextColumn::make('character.name')
                    ->label('Personaggio')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('media_type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('narrative_format')
                    ->label('Formato')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('caption')
                    ->label('Caption')
                    ->limit(60)
                    ->tooltip(fn (?string $state) => $state)
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'draft' => 'gray',
                        'published' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('platform_post_id')
                    ->label('ID post Instagram')
                    ->placeholder('—')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('published_at')
                    ->label('Pubblicato il')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Generato il')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('character_id')
                    ->label('Personaggio')
                    ->relationship('character', 'name'),
                SelectFilter::make('status')
                    ->label('Stato')
                    ->options([
                        'draft' => 'Bozza',
                        'published' => 'Pubblicato',
                        'failed' => 'Fallito',
                    ]),
                SelectFilter::make('media_type')
                    ->label('Tipo')
                    ->options([
                        'image' => 'Immagine',
                        'carousel' => 'Carosello',
                        'story' => 'Story',
                        'reel' => 'Reel',
                    ]),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Vedi')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (Post $record) => $record->character->name)
                    ->modalContent(fn (Post $record) => view('filament.posts.preview-modal', ['post' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi'),
                Action::make('publish')
                    ->label('Pubblica ora')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (Post $record) => $record->status === 'draft')
                    ->requiresConfirmation()
                    ->modalHeading('Pubblicare questo post su Instagram adesso?')
                    ->action(function (Post $record) {
                        try {
                            PublishInstagramPostJob::dispatchSync($record->id);
                            Notification::make()->title('Pubblicato con successo')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Pubblicazione fallita')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
