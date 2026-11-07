<?php

namespace App\Models;

use App\Models\Concerns\GeneratesInventoryCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Service extends Model
{
    use GeneratesInventoryCode, HasFactory;

    protected const CODE_PREFIX = 'SVC';

    protected $guarded = ['id', 'active_instance_id', 'active_marker'];

    protected function casts(): array
    {
        return ['started_at' => 'date', 'archived_at' => 'datetime', 'legacy_data' => 'array'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(ServiceDeployment::class);
    }

    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'service_domain')->using(ServiceDomain::class);
    }

    protected static function booted(): void
    {
        static::creating(function (self $service): void {
            if (filled($service->slug)) {
                return;
            }

            $suffix = (string) now()->getTimestamp();
            $name = Str::slug($service->name) ?: 'service';
            $base = Str::limit($name, 255 - strlen($suffix) - 1, '').'-'.$suffix;
            $slug = $base;
            $counter = 2;

            while (self::query()->where('slug', $slug)->exists()) {
                $suffixWithCounter = '-'.$counter++;
                $slug = Str::limit($base, 255 - strlen($suffixWithCounter), '').$suffixWithCounter;
            }

            $service->slug = $slug;
        });

        static::saving(function (self $service): void {
            if ($service->status === 'archived' && $service->archived_at === null) {
                $service->archived_at = now();
            }
        });
    }
}
