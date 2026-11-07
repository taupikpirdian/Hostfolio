<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class MinioStorage
{
    public function storePaymentProof(UploadedFile $file): string
    {
        $path = $file->store('payment-proofs', 'minio');
        if (! $path) {
            throw new RuntimeException('Bukti pembayaran gagal disimpan ke MinIO.');
        }

        return $path;
    }

    public function temporaryUrl(string $path): string
    {
        return Storage::disk('minio')->temporaryUrl($path, now()->addMinutes(10));
    }

    public function exists(string $path): bool
    {
        return Storage::disk('minio')->exists($path);
    }

    public function delete(string $path): bool
    {
        return Storage::disk('minio')->delete($path);
    }
}
