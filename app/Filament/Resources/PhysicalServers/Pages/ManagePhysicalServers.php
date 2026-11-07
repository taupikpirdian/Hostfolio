<?php

namespace App\Filament\Resources\PhysicalServers\Pages;

use App\Filament\Resources\PhysicalServers\PhysicalServerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePhysicalServers extends ManageRecords
{
    protected static string $resource = PhysicalServerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
