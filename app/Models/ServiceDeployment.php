<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceDeployment extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'active_instance_id', 'active_marker'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'deployed_at' => 'datetime', 'removed_at' => 'datetime'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(ComputeInstance::class, 'compute_instance_id');
    }

    public function computeInstance(): BelongsTo
    {
        return $this->instance();
    }
}
