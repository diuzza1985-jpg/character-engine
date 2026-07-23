<?php
namespace App\Filament\Resources\ScheduledPosts\Pages;
use App\Filament\Resources\ScheduledPosts\ScheduledPostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditScheduledPost extends EditRecord
{
    protected static string $resource = ScheduledPostResource::class;
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
