<?php
namespace App\Filament\Resources\WeeklyScheduleSlots\Schemas;
use App\Models\LifeEvent;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
class WeeklyScheduleSlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('character_id')
                    ->label('Personaggio')
                    ->relationship('character', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('day_of_week')
                    ->label('Giorno della settimana')
                    ->options([
                        0 => 'Domenica', 1 => 'Lunedì', 2 => 'Martedì', 3 => 'Mercoledì',
                        4 => 'Giovedì', 5 => 'Venerdì', 6 => 'Sabato',
                    ])
                    ->required(),
                TimePicker::make('time_of_day')
                    ->label('Ora')
                    ->seconds(false)
                    ->native(false)
                    ->required(),
                Select::make('post_type')
                    ->label('Tipo di contenuto')
                    ->options([
                        'random' => 'Casuale (immagine, carosello o story)',
                        'image' => 'Immagine singola',
                        'carousel' => 'Carosello',
                        'story' => 'Story',
                    ])
                    ->required()
                    ->default('random'),
                Select::make('mode')
                    ->label('Argomento')
                    ->options([
                        'random' => 'Casuale (scelto automaticamente)',
                        'manual' => 'Scelgo io il momento/argomento',
                    ])
                    ->required()
                    ->default('random')
                    ->live(),
                Select::make('life_event_id')
                    ->label('Momento/argomento')
                    ->options(function (callable $get) {
                        $characterId = $get('character_id');
                        if (! $characterId) {
                            return [];
                        }
                        return LifeEvent::where(function ($q) use ($characterId) {
                            $q->where('character_id', $characterId)->orWhereNull('character_id');
                        })->orderBy('title')->pluck('title', 'id');
                    })
                    ->searchable()
                    ->visible(fn (callable $get) => $get('mode') === 'manual')
                    ->required(fn (callable $get) => $get('mode') === 'manual'),
                Textarea::make('instructions')
                    ->label('Istruzioni (facoltative)')
                    ->rows(3),
                Toggle::make('enabled')
                    ->label('Abilitato')
                    ->default(true),
            ]);
    }
}
