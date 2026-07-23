<?php
namespace App\Filament\Resources\ScheduledPosts\Pages;
use App\Filament\Resources\ScheduledPosts\ScheduledPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListScheduledPosts extends ListRecords
{
    protected static string $resource = ScheduledPostResource::class;
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Pubblica un post'),
        ];
    }
}
