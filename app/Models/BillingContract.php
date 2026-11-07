<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class BillingContract extends Model
{
    use HasFactory;

    protected $fillable = ['number', 'name', 'party_id', 'compute_instance_id', 'service_id', 'direction', 'amount', 'currency', 'description', 'start_date', 'end_date', 'starts_at', 'ends_at', 'first_billing_date', 'next_billing_date', 'billing_sequence', 'generate_days_before', 'payment_terms_days', 'auto_generate', 'billing_cycle', 'status', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'auto_generate' => 'boolean', 'start_date' => 'date', 'end_date' => 'date', 'starts_at' => 'date', 'ends_at' => 'date', 'first_billing_date' => 'date', 'next_billing_date' => 'date'];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function computeInstance(): BelongsTo
    {
        return $this->belongsTo(ComputeInstance::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContractItem::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $contract): void {
            if (filled($contract->compute_instance_id) && filled($contract->service_id)) {
                throw ValidationException::withMessages(['target' => 'Kontrak hanya boleh terkait ke satu target.']);
            }
            if (filled($contract->party_id) && filled($contract->direction)) {
                $party = Party::find($contract->party_id);
                $expected = $contract->direction === 'income' ? 'client' : 'vendor';
                if ($party && ! in_array($party->type, [$expected, 'both'], true)) {
                    throw ValidationException::withMessages(['party_id' => 'Jenis party tidak sesuai dengan arah kontrak.']);
                }
            }
            if (filled($contract->amount) && bccomp((string) $contract->amount, '0', 2) < 0) {
                throw ValidationException::withMessages(['amount' => 'Amount tidak boleh kurang dari 0.']);
            }
        });
    }
}
