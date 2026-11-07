<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'active_instance_id', 'active_marker'];

    public function instances(): HasMany
    {
        return $this->hasMany(ComputeInstance::class);
    }

    public function physicalServers(): HasMany
    {
        return $this->hasMany(PhysicalServer::class);
    }
}
