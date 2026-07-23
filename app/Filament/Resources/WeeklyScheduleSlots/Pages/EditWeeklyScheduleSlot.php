<?php
namespace App\Filament\Resources\WeeklyScheduleSlots\Pages;
use App\Filament\Resources\WeeklyScheduleSlots\WeeklyScheduleSlotResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditWeeklyScheduleSlot extends EditRecord
{
    protected static string $resource = WeeklyScheduleSlotResource::class;
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
