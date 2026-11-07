<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = ['invoice_id', 'paid_at', 'amount', 'method', 'reference', 'proof_path', 'notes', 'reversed_at', 'reversal_reason'];

    protected function casts(): array
    {
        return ['paid_at' => 'date', 'amount' => 'decimal:2', 'reversed_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected static function booted(): void
    {
        static::updating(function (self $payment): void {
            if ($payment->isDirty(['invoice_id', 'paid_at', 'amount', 'method', 'reference', 'proof_path', 'notes'])) {
                throw ValidationException::withMessages(['payment' => 'Correct payments through reversal, not editing.']);
            }
        });
        static::deleting(fn () => throw ValidationException::withMessages(['payment' => 'Payments cannot be deleted.']));
    }
}
