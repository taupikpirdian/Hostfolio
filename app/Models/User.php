<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

#[Fillable(['name', 'email', 'password', 'role', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = ['is_active' => true, 'role' => 'viewer'];

    public function save(array $options = []): bool
    {
        return DB::transaction(function () use ($options): bool {
            if ($this->exists && $this->getOriginal('role') === 'admin' && $this->getOriginal('is_active') && ($this->role !== 'admin' || ! $this->is_active)) {
                Role::where('name', 'admin')->lockForUpdate()->firstOrFail();
                if (! static::where('role', 'admin')->where('is_active', true)->whereKeyNot($this->id)->exists()) {
                    throw ValidationException::withMessages(['role' => 'Minimal satu Admin aktif harus dipertahankan.']);
                }
            }

            return parent::save($options);
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function canView(string $area): bool
    {
        return $this->hasAreaPermission($area.'.view');
    }

    public function canManage(string $area): bool
    {
        return $this->hasAreaPermission($area.'.manage');
    }

    private function hasAreaPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $permissions = Role::where('name', $this->role)->value('permissions');
        if (is_string($permissions)) {
            $permissions = json_decode($permissions, true);
        }

        return in_array($permission, $permissions ?? [], true) || in_array('*', $permissions ?? [], true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
