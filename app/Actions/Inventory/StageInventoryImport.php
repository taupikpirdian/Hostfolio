<?php

namespace App\Actions\Inventory;

use App\Models\ImportBatch;
use App\Models\Service;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StageInventoryImport
{
    public function execute(string $csv, array $mapping): ImportBatch
    {
        Gate::authorize('create', ImportBatch::class);
        if (strlen($csv) > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['csv' => 'Maksimum ukuran CSV 10 MB.']);
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $csv));
        rewind($stream);
        $headers = fgetcsv($stream, 0, ',', '"', '');
        if (! $headers || count($headers) !== count(array_unique($headers)) || ! in_array($mapping['name'] ?? null, $headers, true)) {
            fclose($stream);
            throw ValidationException::withMessages(['mapping' => 'Pilih kolom nama service. Header CSV harus unik.']);
        }

        foreach ($mapping as $column) {
            if ($column !== null && ! is_string($column)) {
                fclose($stream);
                throw ValidationException::withMessages(['mapping' => 'Mapping kolom harus berupa nama header.']);
            }
        }

        return DB::transaction(function () use ($stream, $headers, $mapping) {
            $batch = ImportBatch::create(['user_id' => auth()->id(), 'mapping' => $mapping]);
            $seen = [];
            $line = 1;
            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }if ($line > 5001) {
                    throw ValidationException::withMessages(['csv' => 'Maksimum 5000 baris per import.']);
                }
                $errors = [];
                $raw = count($values) === count($headers) ? array_combine($headers, $values) : [];
                if (! $raw) {
                    $errors[] = 'Jumlah kolom tidak cocok.';
                }
                $name = trim($raw[$mapping['name']] ?? '');
                $slug = Str::slug($name);
                if ($name === '' || mb_strlen($name) > 255 || strlen($slug) > 255 || $slug === '') {
                    $errors[] = 'Nama service tidak valid.';
                }
                if (isset($seen[$slug]) || Service::where('slug', $slug)->exists()) {
                    $errors[] = 'Duplikat nama/slug service.';
                }$seen[$slug] = true;
                $status = strtolower(trim($raw[$mapping['status'] ?? ''] ?? 'draft'));
                $status = match ($status) {
                    'deleted','archived' => 'archived','active','running' => 'running','inactive','stopped' => 'stopped','draft','' => 'draft',default => 'invalid'
                };
                if ($status === 'invalid') {
                    $errors[] = 'Status tidak dikenal.';
                }
                $legacy = ['verified' => false];
                foreach (['reference', 'server', 'vcpu', 'ram_mb', 'storage_gb', 'start_date', 'end_date', 'amount'] as $field) {
                    $value = trim($raw[$mapping[$field] ?? ''] ?? '');
                    $legacy[$field] = in_array($value, ['', '-'], true) ? null : $value;
                }
                $batch->rows()->create(['row_number' => $line, 'raw' => $raw, 'normalized' => ['name' => $name, 'slug' => $slug, 'status' => $status, 'legacy_data' => $legacy], 'errors' => $errors]);
            }fclose($stream);
            if (! $batch->rows()->exists()) {
                throw ValidationException::withMessages(['csv' => 'CSV tidak memiliki baris data.']);
            }

            return $batch;
        });
    }
}
