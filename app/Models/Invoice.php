<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class Invoice extends Model
{
    use HasFactory;

    private bool $transitioning = false;

    private bool $deletingDraft = false;

    protected $fillable = ['category_id', 'source_currency', 'source_amount', 'exchange_rate', 'exchange_rate_source', 'number', 'type', 'party_id', 'billing_contract_id', 'issue_date', 'due_date', 'period_start', 'period_end', 'status', 'subtotal', 'discount', 'tax', 'total', 'notes'];

    protected function casts(): array
    {
        return ['source_amount' => 'decimal:2', 'exchange_rate' => 'decimal:6', 'issue_date' => 'date', 'due_date' => 'date', 'period_start' => 'date', 'period_end' => 'date', 'subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(BillingContract::class, 'billing_contract_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getPaidTotalAttribute(): string
    {
        $total = '0.00';
        foreach (($this->relationLoaded('payments') ? $this->payments->whereNull('reversed_at')->pluck('amount') : $this->payments()->whereNull('reversed_at')->pluck('amount')) as $amount) {
            $total = bcadd($total, (string) $amount, 2);
        }

        return $total;
    }

    public function getOutstandingAttribute(): string
    {
        return $this->status === 'issued' ? bcsub($this->total, $this->paid_total, 2) : '0.00';
    }

    public function getPaymentStatusAttribute(): string
    {
        if (bccomp($this->paid_total, '0', 2) === 0) {
            return 'unpaid';
        }

        return bccomp($this->outstanding, '0', 2) === 0 ? 'paid' : 'partial';
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'issued' && $this->due_date->startOfDay()->lt(today()) && bccomp($this->outstanding, '0', 2) > 0;
    }

    public function transitionTo(string $status, array $totals = []): void
    {
        Gate::authorize('update', $this);
        $this->applyTransition($status, $totals);
    }

    public function issueAutomatically(): void
    {
        $this->applyTransition('issued', []);
    }

    private function applyTransition(string $status, array $totals): void
    {
        if (! in_array($status, ['issued', 'void'], true) || ($status === 'issued' && $this->status !== 'draft') || $this->status === 'void') {
            throw ValidationException::withMessages(['status' => 'Invalid invoice transition.']);
        }
        $this->transitioning = true;
        try {
            $this->update($totals + ['status' => $status]);
        } finally {
            $this->transitioning = false;
        }
    }

    public function deleteDraft(): void
    {
        Gate::authorize('update', $this);
        if ($this->status !== 'draft' || $this->payments()->exists()) {
            throw ValidationException::withMessages(['invoice' => 'Hanya draft tanpa pembayaran yang dapat dihapus.']);
        }
        $this->deletingDraft = true;
        try {
            DB::transaction(function (): void {
                $this->items()->delete();
                $this->delete();
            });
        } finally {
            $this->deletingDraft = false;
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $invoice): void {
            Validator::make($invoice->getAttributes(), [
                'category_id' => 'nullable|exists:categories,id',
                'source_currency' => 'nullable|regex:/^[A-Z]{3}$/',
                'source_amount' => ['nullable', Rule::requiredIf(($invoice->source_currency ?? 'IDR') !== 'IDR'), 'numeric', 'gt:0'],
                'exchange_rate' => ['nullable', Rule::requiredIf(($invoice->source_currency ?? 'IDR') !== 'IDR'), 'numeric', 'gt:0'],
                'exchange_rate_source' => ['nullable', Rule::requiredIf(($invoice->source_currency ?? 'IDR') !== 'IDR'), 'string', 'max:255'],
            ])->validate();
        });
        static::updating(function (self $invoice): void {
            if ($invoice->isDirty('status') && ! $invoice->transitioning) {
                throw ValidationException::withMessages(['status' => 'Use the issue or void workflow.']);
            }
            if ($invoice->getOriginal('status') !== 'draft' && $invoice->isDirty(['category_id', 'source_currency', 'source_amount', 'exchange_rate', 'exchange_rate_source', 'number', 'type', 'party_id', 'billing_contract_id', 'issue_date', 'due_date', 'period_start', 'period_end', 'subtotal', 'discount', 'tax', 'total'])) {
                throw ValidationException::withMessages(['invoice' => 'Issued invoice snapshots cannot be changed.']);
            }
        });
        static::deleting(function (self $invoice): void {
            if (! $invoice->deletingDraft) {
                throw ValidationException::withMessages(['invoice' => 'Only draft invoices can be deleted.']);
            }
        });
    }
}
