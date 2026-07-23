<?php
namespace App\Filament\Resources\SceneElements;
use App\Filament\Resources\SceneElements\Pages\CreateSceneElement;
use App\Filament\Resources\SceneElements\Pages\EditSceneElement;
use App\Filament\Resources\SceneElements\Pages\ListSceneElements;
use App\Filament\Resources\SceneElements\Schemas\SceneElementForm;
use App\Filament\Resources\SceneElements\Tables\SceneElementsTable;
use App\Models\SceneElement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
class SceneElementResource extends Resource
{
    protected static ?string $model = SceneElement::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'Elemento di scena';
    protected static ?string $pluralModelLabel = 'Libreria scene';
    public static function form(Schema $schema): Schema
    {
        return SceneElementForm::configure($schema);
    }
    public static function table(Table $table): Table
    {
        return SceneElementsTable::configure($table);
    }
    public static function getRelations(): array
    {
        return [
            //
        ];
    }
    public static function getPages(): array
    {
        return [
            'index' => ListSceneElements::route('/'),
            'create' => CreateSceneElement::route('/create'),
            'edit' => EditSceneElement::route('/{record}/edit'),
        ];
    }
}