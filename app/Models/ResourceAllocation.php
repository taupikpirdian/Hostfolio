<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceAllocation extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'active_instance_id', 'active_marker'];

    protected $table = 'resource_allocation_history';

    protected function casts(): array
    {
        return ['vcpu' => 'decimal:2', 'ram_mb' => 'integer', 'storage_gb' => 'decimal:2', 'effective_from' => 'datetime', 'effective_to' => 'datetime'];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ComputeInstance::class, 'compute_instance_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function computeInstance(): BelongsTo
    {
        return $this->instance();
    }
}
