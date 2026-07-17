<?php

namespace App\Filament\Resources\Characters;

use App\Filament\Resources\Characters\Pages\CreateCharacter;
use App\Filament\Resources\Characters\Pages\EditCharacter;
use App\Filament\Resources\Characters\Pages\ListCharacters;
use App\Filament\Resources\Characters\Schemas\CharacterForm;
use App\Filament\Resources\Characters\Tables\CharactersTable;
use App\Models\Character;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CharacterResource extends Resource
{
    protected static ?string $model = Character::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Personaggio';

    protected static ?string $pluralModelLabel = 'Personaggi';

    public static function form(Schema $schema): Schema
    {
        return CharacterForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CharactersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Characters\RelationManagers\BibleSectionsRelationManager::class,
            \App\Filament\Resources\Characters\RelationManagers\AssetsRelationManager::class,
            \App\Filament\Resources\Characters\RelationManagers\SceneElementsRelationManager::class,
            \App\Filament\Resources\Characters\RelationManagers\VisualProfileRelationManager::class,
            \App\Filament\Resources\Characters\RelationManagers\EditorialSettingsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCharacters::route('/'),
            'create' => CreateCharacter::route('/create'),
            'edit' => EditCharacter::route('/{record}/edit'),
        ];
    }
}
