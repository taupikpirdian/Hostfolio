<?php

namespace App\Models;

use App\Models\Concerns\GeneratesInventoryCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComputeInstance extends Model
{
    use GeneratesInventoryCode, HasFactory;

    protected const CODE_PREFIX = 'CMP';

    protected $guarded = ['id', 'active_instance_id', 'active_marker'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function physicalServer(): BelongsTo
    {
        return $this->belongsTo(PhysicalServer::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ResourceAllocation::class);
    }

    public function billingContracts(): HasMany
    {
        return $this->hasMany(BillingContract::class);
    }

    public function currentAllocation(): HasOne
    {
        return $this->hasOne(ResourceAllocation::class)->whereNull('effective_to');
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(ServiceDeployment::class);
    }

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            $instances = self::query()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $visited = [];
            $parentId = $this->parent_id;
            $hostId = $this->physical_server_id;
            while ($parentId !== null) {
                if ($parentId == $this->id || isset($visited[$parentId])) {
                    throw ValidationException::withMessages(['parent_id' => 'Parent instance cannot form a circular dependency.']);
                }
                $visited[$parentId] = true;
                $parent = $instances->get($parentId);
                if ($parent === null) {
                    throw ValidationException::withMessages(['parent_id' => 'Parent instance does not exist.']);
                }
                if ($hostId !== null && $parent->physical_server_id !== null && $hostId != $parent->physical_server_id) {
                    throw ValidationException::withMessages(['physical_server_id' => 'Child and parent instances must use the same physical server.']);
                }
                $hostId ??= $parent->physical_server_id;
                $parentId = $parent->parent_id;
            }
            if ($this->exists && $this->isDirty(['physical_server_id', 'parent_id'])) {
                foreach ($instances->where('parent_id', $this->id) as $child) {
                    if ($hostId !== null && $child->physical_server_id !== null && $hostId != $child->physical_server_id) {
                        throw ValidationException::withMessages(['physical_server_id' => 'Update child host mappings before relocating this parent.']);
                    }
                }
            }

            return parent::save($options);
        });
    }
}
