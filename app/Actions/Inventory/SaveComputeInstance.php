<?php

namespace App\Actions\Inventory;

use App\Models\ComputeInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveComputeInstance
{
    public function execute(array $data, ?ComputeInstance $instance = null): ComputeInstance
    {
        Gate::authorize($instance ? 'update' : 'create', $instance ?? ComputeInstance::class);

        return DB::transaction(function () use ($data, $instance): ComputeInstance {
            ComputeInstance::query()->orderBy('id')->lockForUpdate()->get(['id']);
            $instance = $instance ? ComputeInstance::query()->findOrFail($instance->id) : new ComputeInstance;
            if (! $instance->exists && blank($data['code'] ?? null)) {
                $data['code'] = ComputeInstance::generateCode();
            }
            $validated = Validator::make(array_merge($instance->getAttributes(), $data), [
                'code' => ['required', 'string', 'max:255', Rule::unique('compute_instances')->ignore($instance->id)],
                'name' => 'required|string|max:255',
                'type' => ['required', Rule::in(['vm', 'lxc', 'docker', 'vps', 'shared_hosting'])],
                'status' => ['required', Rule::in(['planned', 'active', 'inactive', 'retired'])],
                'provider_id' => 'nullable|exists:providers,id', 'physical_server_id' => 'nullable|exists:physical_servers,id', 'parent_id' => 'nullable|exists:compute_instances,id',
                'hostname' => 'nullable|string|max:255', 'ip_address' => 'nullable|ip', 'operating_system' => 'nullable|string|max:255', 'external_reference' => 'nullable|string|max:255', 'notes' => 'nullable|string',
            ])->validate();
            $visited = [];
            $parent = $validated['parent_id'] ?? null;
            while ($parent !== null) {
                if ($parent == $instance->id || isset($visited[$parent])) {
                    throw ValidationException::withMessages(['parent_id' => 'Parent instance cannot form a circular dependency.']);
                }
                $visited[$parent] = true;
                $parent = ComputeInstance::query()->findOrFail($parent)->parent_id;
            }
            $instance->fill($validated)->save();

            return $instance;
        });
    }
}
