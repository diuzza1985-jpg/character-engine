<?php

namespace App\Filament\Resources\SceneElements\Pages;

use App\Filament\Resources\SceneElements\SceneElementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSceneElement extends EditRecord
{
    protected static string $resource = SceneElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
