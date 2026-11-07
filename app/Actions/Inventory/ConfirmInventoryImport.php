<?php

namespace App\Actions\Inventory;

use App\Models\ComputeInstance;
use App\Models\ImportBatch;
use App\Models\Service;
use App\Models\ServiceDeployment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ConfirmInventoryImport
{
    public function execute(ImportBatch $batch, array $instanceMapping = []): void
    {
        Gate::authorize('update', $batch);
        Validator::make(['instances' => $instanceMapping], ['instances' => 'array', 'instances.*' => 'required|integer|exists:compute_instances,id'])->validate();
        DB::transaction(function () use ($batch, $instanceMapping) {
            $batch = ImportBatch::lockForUpdate()->findOrFail($batch->id);
            if ($batch->status !== 'staged') {
                throw ValidationException::withMessages(['batch' => 'Batch sudah dikonfirmasi.']);
            }
            foreach ($batch->rows()->orderBy('id')->get() as $row) {
                $data = $row->normalized;
                if ($row->errors || Service::where('slug', $data['slug'])->exists()) {
                    throw ValidationException::withMessages(['batch' => 'Selesaikan kesalahan dan duplikasi sebelum import.']);
                }
                if (empty($instanceMapping[$row->id])) {
                    throw ValidationException::withMessages(['batch' => 'Pilih Compute Instance untuk setiap service sebelum import.']);
                }
                $instance = ComputeInstance::findOrFail($instanceMapping[$row->id]);
                $service = Service::create([...$data, 'code' => 'IMP-'.$batch->id.'-'.$row->row_number, 'type' => 'other', 'archived_at' => $data['status'] === 'archived' ? now() : null, 'notes' => 'Legacy import #'.$batch->id.' baris '.$row->row_number.'. Resource, tanggal dan amount perlu verifikasi.']);
                if ($data['status'] !== 'archived') {
                    app(DeployService::class)->execute($service, $instance, ['environment' => 'production']);
                } else {
                    Gate::authorize('create', ServiceDeployment::class);
                    $service->deployments()->create(['compute_instance_id' => $instance->id, 'environment' => 'production', 'deployed_at' => now(), 'removed_at' => now(), 'notes' => 'Penempatan historis dari import legacy.']);
                }
                $row->update(['service_id' => $service->id]);
            }
            $batch->update(['status' => 'confirmed', 'confirmed_at' => now()]);
        });
    }
}
