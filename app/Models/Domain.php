<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Domain extends Model
{
    use HasFactory;

    protected $guarded = ['id', 'active_instance_id', 'active_marker'];

    protected function casts(): array
    {
        return ['expires_at' => 'date', 'auto_renew' => 'boolean'];
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_domain')->using(ServiceDomain::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $domain): void {
            $domain->fqdn = strtolower(rtrim(trim($domain->fqdn), '.'));
        });
    }
}
