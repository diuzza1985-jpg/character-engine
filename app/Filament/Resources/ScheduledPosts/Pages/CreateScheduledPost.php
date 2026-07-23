<?php
namespace App\Filament\Resources\ScheduledPosts\Pages;
use App\Filament\Resources\ScheduledPosts\ScheduledPostResource;
use App\Jobs\ProcessScheduledPostJob;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Throwable;
class CreateScheduledPost extends CreateRecord
{
    protected static string $resource = ScheduledPostResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'pending';
        return $data;
    }
    protected function afterCreate(): void
    {
        if ($this->record->scheduled_at->lessThanOrEqualTo(now()->addMinute())) {
            try {
                ProcessScheduledPostJob::dispatchSync($this->record->id);
                $this->record->refresh();
                Notification::make()
                    ->title('Pubblicazione completata')
                    ->success()
                    ->send();
            } catch (Throwable $e) {
                Notification::make()
                    ->title('Generazione o pubblicazione fallita')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }
    }
}
