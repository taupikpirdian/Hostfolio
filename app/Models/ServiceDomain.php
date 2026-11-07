<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

class ServiceDomain extends Pivot
{
    public $incrementing = true;

    public $timestamps = false;

    protected $table = 'service_domain';

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            $domain = Domain::query()->lockForUpdate()->findOrFail($this->domain_id);
            $before = $this->serviceIds($domain);
            $creating = ! $this->exists;
            $saved = parent::save($options);
            if ($saved && $creating) {
                $this->recordChange($domain, 'domain.service_attached', $before);
            }

            return $saved;
        });
    }

    public function delete(): int
    {
        return DB::transaction(function (): int {
            $domain = Domain::query()->lockForUpdate()->findOrFail($this->domain_id);
            $before = $this->serviceIds($domain);
            $deleted = (int) parent::delete();
            if ($deleted > 0) {
                $this->recordChange($domain, 'domain.service_detached', $before);
            }

            return $deleted;
        });
    }

    /** @return array<int, int> */
    private function serviceIds(Domain $domain): array
    {
        return $domain->services()->orderBy('services.id')->pluck('services.id')->all();
    }

    /** @param array<int, int> $before */
    private function recordChange(Domain $domain, string $event, array $before): void
    {
        AuditLog::record($event, $domain, [
            'before' => ['service_ids' => $before],
            'after' => ['service_ids' => $this->serviceIds($domain)],
        ]);
    }
}
