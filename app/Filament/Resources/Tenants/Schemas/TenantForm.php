<?php

namespace App\Filament\Resources\Tenants\Schemas;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required(),
                Select::make('plan_id')
                    ->label('Piano')
                    ->relationship('plan', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('status')
                    ->label('Stato')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ])
                    ->required()
                    ->default('trial'),
            ]);
    }
}