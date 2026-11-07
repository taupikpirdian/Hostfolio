<?php

namespace App\Filament\Resources\ApplicationSettings\Pages;

use App\Filament\Resources\ApplicationSettings\ApplicationSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageApplicationSettings extends ManageRecords
{
    protected static string $resource = ApplicationSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
