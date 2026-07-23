<?php
namespace App\Filament\Resources\ScheduledPosts;
use App\Filament\Resources\ScheduledPosts\Pages\CreateScheduledPost;
use App\Filament\Resources\ScheduledPosts\Pages\EditScheduledPost;
use App\Filament\Resources\ScheduledPosts\Pages\ListScheduledPosts;
use App\Filament\Resources\ScheduledPosts\Schemas\ScheduledPostForm;
use App\Filament\Resources\ScheduledPosts\Tables\ScheduledPostsTable;
use App\Models\ScheduledPost;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
class ScheduledPostResource extends Resource
{
    protected static ?string $model = ScheduledPost::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Pubblicazioni';
    protected static ?string $modelLabel = 'Pubblicazione';
    protected static ?string $pluralModelLabel = 'Pubblicazioni';
    protected static string|\UnitEnum|null $navigationGroup = 'Pubblicazione';
    public static function form(Schema $schema): Schema
    {
        return ScheduledPostForm::configure($schema);
    }
    public static function table(Table $table): Table
    {
        return ScheduledPostsTable::configure($table);
    }
    public static function getPages(): array
    {
        return [
            'index' => ListScheduledPosts::route('/'),
            'create' => CreateScheduledPost::route('/create'),
            'edit' => EditScheduledPost::route('/{record}/edit'),
        ];
    }
}
