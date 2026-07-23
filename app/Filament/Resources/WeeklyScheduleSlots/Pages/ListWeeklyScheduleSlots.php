<?php
namespace App\Filament\Resources\WeeklyScheduleSlots\Pages;
use App\Filament\Resources\WeeklyScheduleSlots\WeeklyScheduleSlotResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;
class ListWeeklyScheduleSlots extends ListRecords
{
    protected static string $resource = WeeklyScheduleSlotResource::class;
    protected function getHeaderActions(): array
    {
        return [
            Action::make('materialize')
                ->label('Materializza ora')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Genera subito gli scheduled_posts per gli slot di oggi (normalmente avviene automaticamente ogni notte alle 00:05).')
                ->action(function () {
                    Artisan::call('schedule:materialize-weekly');
                    Notification::make()
                        ->title('Piano settimanale materializzato')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
