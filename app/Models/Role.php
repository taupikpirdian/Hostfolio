<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class Role extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $role): void {
            if ($role->isDirty('name')) {
                throw ValidationException::withMessages(['name' => 'Nama role yang sudah dibuat tidak dapat diubah.']);
            }
            if ($role->getOriginal('name') === 'admin' && ($role->name !== 'admin' || ! in_array('*', $role->permissions ?? [], true))) {
                throw ValidationException::withMessages(['permissions' => 'Admin harus memiliki seluruh permission.']);
            }
        });
    }

    protected $guarded = [];

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }
}
