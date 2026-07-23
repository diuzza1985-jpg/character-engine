<?php

namespace App\Filament\Resources\Characters\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CharacterForm
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
                    ->required(),
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $context, $state, callable $set) {
                        if ($context === 'create') {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Slug')
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('status')
                    ->label('Stato')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Attivo',
                        'paused' => 'Pausa',
                    ])
                    ->required()
                    ->default('draft'),
            ]);
    }
}
