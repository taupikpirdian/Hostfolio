<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContractItem extends Model
{
    use HasFactory;

    protected $fillable = ['billing_contract_id', 'description', 'quantity', 'unit_price', 'target_type', 'target_id', 'effective_from'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:2', 'effective_from' => 'date'];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(BillingContract::class, 'billing_contract_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
