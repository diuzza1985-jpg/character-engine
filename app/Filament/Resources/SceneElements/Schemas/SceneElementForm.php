<?php

namespace App\Filament\Resources\SceneElements\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SceneElementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tenant_id')
                    ->label('Cliente')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload()
                    ->helperText('Lascia vuoto per renderlo disponibile a tutti i clienti (libreria condivisa)'),
                Select::make('category')
                    ->label('Categoria')
                    ->options([
                        'location' => 'Location',
                        'prop' => 'Oggetto',
                        'mood' => 'Mood',
                        'lighting' => 'Luce',
                        'camera' => 'Inquadratura',
                        'activity' => 'Attività',
                    ])
                    ->required(),
                TextInput::make('key')
                    ->label('Chiave tecnica')
                    ->helperText('Identificativo interno, senza spazi (es: home_office)')
                    ->required(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                Textarea::make('description')
                    ->label('Descrizione')
                    ->columnSpanFull(),
                Textarea::make('rules_text')
                    ->label('Regole')
                    ->columnSpanFull(),
                Textarea::make('camera_notes')
                    ->label('Note inquadratura')
                    ->columnSpanFull(),
                Textarea::make('lighting_notes')
                    ->label('Note luce')
                    ->columnSpanFull(),
                Select::make('image_asset_id')
                    ->label('Foto di riferimento')
                    ->relationship('imageAsset', 'type')
                    ->searchable()
                    ->preload()
                    ->helperText('Facoltativo: collega una foto già caricata tra gli asset di un personaggio'),
            ]);
    }
}
