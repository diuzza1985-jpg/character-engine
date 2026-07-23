<?php

namespace App\Filament\Resources\SceneElements\Pages;

use App\Filament\Resources\SceneElements\SceneElementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSceneElements extends ListRecords
{
    protected static string $resource = SceneElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
