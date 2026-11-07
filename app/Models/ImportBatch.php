<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['mapping' => 'array', 'confirmed_at' => 'datetime'];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class);
    }
}
