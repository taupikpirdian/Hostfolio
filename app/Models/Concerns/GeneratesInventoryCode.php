<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait GeneratesInventoryCode
{
    public static function bootGeneratesInventoryCode(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->code)) {
                $model->code = static::generateCode();
            }
        });
    }

    public static function generateCode(): string
    {
        return static::CODE_PREFIX.'-'.Str::ulid();
    }
}
