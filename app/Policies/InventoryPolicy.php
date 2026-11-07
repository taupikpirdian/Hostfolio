<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class InventoryPolicy
{
    protected string $area = 'inventory';

    public function viewAny(User $user): bool
    {
        return $user->canView($this->area);
    }

    public function view(User $user, Model $record): bool
    {
        return $user->canView($this->area);
    }

    public function create(User $user): bool
    {
        return $user->canManage($this->area);
    }

    public function update(User $user, Model $record): bool
    {
        return $user->canManage($this->area);
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Model $record): bool
    {
        return false;
    }

    public function forceDelete(User $user, Model $record): bool
    {
        return false;
    }
}
