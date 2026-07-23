<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('price_monthly')
                    ->label('Prezzo mensile')
                    ->required()
                    ->numeric()
                    ->default(0.0),
                TextInput::make('credits_included')
                    ->label('Crediti inclusi')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('max_characters')
                    ->label('Personaggi max')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('max_generations_per_day')
                    ->label('Generazioni max/giorno')
                    ->numeric(),
                TextInput::make('stripe_price_id')
                    ->label('ID prezzo Stripe')
                    ->helperText('Si collega quando il piano è configurato su Stripe'),
            ]);
    }
}
