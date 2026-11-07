<?php

namespace App\Filament\Resources\ComputeInstances\Pages;

use App\Filament\Resources\ComputeInstances\ComputeInstanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageComputeInstances extends ManageRecords
{
    protected static string $resource = ComputeInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->using(fn (array $data): Model => ComputeInstanceResource::saveWithAllocation($data))];
    }
}
