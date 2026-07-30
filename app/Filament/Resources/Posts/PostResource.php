<?php

namespace App\Filament\Resources\Posts;

use App\Filament\Resources\Posts\Pages\ListPosts;
use App\Filament\Resources\Posts\Tables\PostsTable;
use App\Models\Post;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Table;

/**
 * Sola lettura (nessuna Create/Edit): un Post nasce sempre dalla pipeline di generazione
 * (GenerateInstagramPostJob), mai da un form admin — questa vista serve solo a vedere lo storico
 * reale di tutto ciò che è stato generato finora, gap segnalato in project.md sez. 12 punto 6
 * ("viste generations/posts"). Distinta da ScheduledPostResource ("Pubblicazioni"): quella elenca
 * gli SLOT programmati (tabella scheduled_posts, quasi sempre vuota/una riga), questa i Post
 * realmente prodotti (28 righe reali oggi), qualunque sia stata la via di generazione.
 */
class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?string $navigationLabel = 'Contenuti generati';

    protected static ?string $modelLabel = 'contenuto';

    protected static ?string $pluralModelLabel = 'contenuti generati';

    protected static string|\UnitEnum|null $navigationGroup = 'Pubblicazione';

    public static function table(Table $table): Table
    {
        return PostsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPosts::route('/'),
        ];
    }
}
