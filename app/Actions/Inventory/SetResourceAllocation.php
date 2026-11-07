<?php

namespace App\Actions\Inventory;

use App\Models\ComputeInstance;
use App\Models\ResourceAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SetResourceAllocation
{
    public function execute(ComputeInstance $instance, array $data): ResourceAllocation
    {
        Gate::authorize('update', $instance);
        $validated = Validator::make($data, [
            'vcpu' => 'nullable|numeric|min:0|max:99999999',
            'ram_mb' => 'nullable|integer|min:0',
            'storage_gb' => 'nullable|numeric|min:0|max:999999999999',
            'effective_from' => 'nullable|date|before_or_equal:now', 'notes' => 'nullable|string',
        ])->validate();

        return DB::transaction(function () use ($instance, $validated): ResourceAllocation {
            $instance = ComputeInstance::query()->lockForUpdate()->findOrFail($instance->id);
            $at = Carbon::parse($validated['effective_from'] ?? now());
            $current = $instance->currentAllocation()->first();
            if ($current && $at->lt($current->effective_from)) {
                throw ValidationException::withMessages(['effective_from' => 'New allocation cannot predate the current allocation.']);
            }
            if ($current) {
                $current->update(['effective_to' => $at]);
            }

            return $instance->allocations()->create([
                'vcpu' => $validated['vcpu'] ?? null, 'ram_mb' => $validated['ram_mb'] ?? null,
                'storage_gb' => $validated['storage_gb'] ?? null, 'effective_from' => $at,
                'changed_by' => auth()->id(), 'notes' => $validated['notes'] ?? null,
            ]);
        });
    }
}
