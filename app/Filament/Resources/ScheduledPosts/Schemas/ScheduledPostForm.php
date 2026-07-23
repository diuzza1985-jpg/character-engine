<?php
namespace App\Filament\Resources\ScheduledPosts\Schemas;
use App\Models\LifeEvent;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
class ScheduledPostForm
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
                    ->label('Istruzioni per oggi (facoltative)')
                    ->helperText('Indicazioni libere passate all\'IA in fase di generazione, es. "parla del weekend appena trascorso"')
                    ->rows(3),
                DateTimePicker::make('scheduled_at')
                    ->label('Pubblica il')
                    ->helperText('Lascia l\'orario attuale per pubblicare subito, oppure scegli una data/ora futura per programmare (verrà elaborata entro 15 minuti dall\'orario scelto).')
                    ->native(false)
                    ->seconds(false)
                    ->default(now())
                    ->required(),
            ]);
    }
}
