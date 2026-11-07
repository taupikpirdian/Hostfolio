<?php

namespace App\Models;

use App\Models\Concerns\GeneratesInventoryCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhysicalServer extends Model
{
    use GeneratesInventoryCode, HasFactory;

    protected const CODE_PREFIX = 'HOST';

    protected $guarded = ['id', 'active_instance_id', 'active_marker'];

    protected function casts(): array
    {
        return ['cpu_cores' => 'integer', 'ram_mb' => 'integer', 'storage_gb' => 'decimal:2'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(ComputeInstance::class);
    }

    public function directInstances(): HasMany
    {
        return $this->instances()->whereNull('parent_id');
    }

    public function allocationSummary(): array
    {
        $this->loadMissing('directInstances.currentAllocation');
        $rows = $this->directInstances->pluck('currentAllocation')->filter();
        $vcpu = (float) $rows->sum('vcpu');

        return ['vcpu' => $vcpu, 'ram_mb' => (int) $rows->sum('ram_mb'), 'storage_gb' => (float) $rows->sum('storage_gb'), 'cpu_overcommitted' => $this->cpu_cores !== null && $vcpu > $this->cpu_cores];
    }
}
