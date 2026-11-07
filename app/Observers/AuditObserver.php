<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function created(Model $model): void
    {
        AuditLog::record('created', $model, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        AuditLog::record('updated', $model, ['before' => collect($model->getRawOriginal())->except(['password', 'remember_token'])->all(), 'after' => collect($model->getChanges())->except(['password', 'remember_token'])->all()]);
    }

    public function deleted(Model $model): void
    {
        AuditLog::record('deleted', $model);
    }
}
