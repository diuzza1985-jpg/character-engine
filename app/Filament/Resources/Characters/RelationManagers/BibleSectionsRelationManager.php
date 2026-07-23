<?php

namespace App\Filament\Resources\Characters\RelationManagers;

use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BibleSectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'bibleSections';

    protected static ?string $title = 'Sezioni bible';

    protected static ?string $modelLabel = 'sezione';

    protected static ?string $pluralModelLabel = 'sezioni';

    public static function sectionOptions(): array
    {
        return [
            'manifesto' => 'Manifesto',
            'persona' => 'Persona',
            'biografia' => 'Biografia',
            'timeline' => 'Timeline',
            'voce' => 'Voce',
            'lessico' => 'Lessico',
            'famiglia' => 'Famiglia',
            'stile' => 'Stile',
            'valori' => 'Valori',
            'umorismo' => 'Umorismo',
            'social' => 'Social',
            'editoriale' => 'Editoriale',
            'missione' => 'Missione',
            'memoria_lungo_termine' => 'Memoria lungo termine',
            'regole' => 'Regole',
            'system_prompt' => 'System prompt',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('section_key')
                    ->label('Sezione')
                    ->options(self::sectionOptions())
                    ->searchable()
                    ->required(),
                Textarea::make('content')
                    ->label('Contenuto')
                    ->rows(15)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('section_key')
            ->columns([
                TextColumn::make('section_key')
                    ->label('Sezione')
                    ->formatStateUsing(fn (string $state): string => self::sectionOptions()[$state] ?? $state)
                    ->searchable(),
                TextColumn::make('version')
                    ->label('Versione')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Aggiornato il')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}