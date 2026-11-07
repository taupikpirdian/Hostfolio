<?php

namespace App\Filament\Resources\ServiceDeployments\Pages;

use App\Actions\Inventory\DeployService;
use App\Filament\Resources\ServiceDeployments\ServiceDeploymentResource;
use App\Models\ComputeInstance;
use App\Models\Service;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageServiceDeployments extends ManageRecords
{
    protected static string $resource = ServiceDeploymentResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->using(fn (array $data): Model => app(DeployService::class)->execute(Service::query()->findOrFail($data['service_id']), ComputeInstance::query()->findOrFail($data['compute_instance_id']), $data))];
    }
}
