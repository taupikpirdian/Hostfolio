<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['changes' => 'array'];
    }

    public static function record(string $event, Model $subject, array $changes = []): self
    {
        return static::create(['event' => $event, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey(), 'user_id' => auth()->id(), 'changes' => collect($changes)->except(['password', 'remember_token', 'proof_path'])->all(), 'ip_address' => app()->runningInConsole() ? null : request()->ip()]);
    }
}
