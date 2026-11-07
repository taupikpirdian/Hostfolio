<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\ValidationException;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = ['invoice_id', 'description', 'quantity', 'unit_price', 'discount', 'tax', 'total', 'target_type', 'target_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:2', 'discount' => 'decimal:2', 'tax' => 'decimal:2', 'total' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        $check = function (self $item): void {
            if ($item->exists && $item->isDirty('invoice_id')) {
                throw ValidationException::withMessages(['items' => 'Invoice items cannot be moved between documents.']);
            }
            if ($item->invoice()->value('status') !== 'draft') {
                throw ValidationException::withMessages(['items' => 'Issued invoice items cannot be changed.']);
            }
        };
        static::saving($check);
        static::deleting($check);
    }
}
