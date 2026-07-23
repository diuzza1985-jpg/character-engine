<?php
namespace App\Filament\Resources\WeeklyScheduleSlots;
use App\Filament\Resources\WeeklyScheduleSlots\Pages\CreateWeeklyScheduleSlot;
use App\Filament\Resources\WeeklyScheduleSlots\Pages\EditWeeklyScheduleSlot;
use App\Filament\Resources\WeeklyScheduleSlots\Pages\ListWeeklyScheduleSlots;
use App\Filament\Resources\WeeklyScheduleSlots\Schemas\WeeklyScheduleSlotForm;
use App\Filament\Resources\WeeklyScheduleSlots\Tables\WeeklyScheduleSlotsTable;
use App\Models\WeeklyScheduleSlot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
class WeeklyScheduleSlotResource extends Resource
{
    protected static ?string $model = WeeklyScheduleSlot::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel = 'Piano settimanale';
    protected static ?string $modelLabel = 'Slot settimanale';
    protected static ?string $pluralModelLabel = 'Piano settimanale';
    protected static string|\UnitEnum|null $navigationGroup = 'Pubblicazione';
    public static function form(Schema $schema): Schema
    {
        return WeeklyScheduleSlotForm::configure($schema);
    }
    public static function table(Table $table): Table
    {
        return WeeklyScheduleSlotsTable::configure($table);
    }
    public static function getPages(): array
    {
        return [
            'index' => ListWeeklyScheduleSlots::route('/'),
            'create' => CreateWeeklyScheduleSlot::route('/create'),
            'edit' => EditWeeklyScheduleSlot::route('/{record}/edit'),
        ];
    }
}
