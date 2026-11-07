<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'type', 'email', 'phone', 'address', 'tax_information', 'notes', 'status'];

    protected function casts(): array
    {
        return [];
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(BillingContract::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
