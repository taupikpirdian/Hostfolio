<?php

namespace App\Actions\Inventory;

use App\Models\ComputeInstance;
use App\Models\Service;
use App\Models\ServiceDeployment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DeployService
{
    public function execute(Service $service, ComputeInstance $instance, array $data = []): ServiceDeployment
    {
        Gate::authorize('create', ServiceDeployment::class);
        $validated = Validator::make($data, [
            'environment' => ['sometimes', Rule::in(['production', 'staging', 'development'])],
            'role' => 'nullable|string|max:255', 'is_primary' => 'sometimes|boolean',
            'deployed_at' => 'nullable|date|before_or_equal:now', 'notes' => 'nullable|string',
        ])->validate();

        return DB::transaction(function () use ($service, $instance, $validated): ServiceDeployment {
            $service = Service::query()->lockForUpdate()->findOrFail($service->id);
            if ($service->status === 'archived') {
                throw ValidationException::withMessages(['service_id' => 'Archived services cannot receive new deployments.']);
            }
            $environment = $validated['environment'] ?? 'production';
            if ($service->deployments()->where('compute_instance_id', $instance->id)->where('environment', $environment)->whereNull('removed_at')->exists()) {
                throw ValidationException::withMessages(['compute_instance_id' => 'An active deployment already exists for this service, instance and environment.']);
            }

            return $service->deployments()->create(array_merge($validated, [
                'compute_instance_id' => $instance->id, 'environment' => $environment, 'deployed_at' => $validated['deployed_at'] ?? now(),
            ]));
        });
    }
}
