<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    public function __invoke(Payment $payment): StreamedResponse
    {
        Gate::authorize('view', $payment);
        abort_unless($payment->proof_path && str_starts_with($payment->proof_path, 'payment-proofs/') && ! str_contains($payment->proof_path, '..'), 404);
        $disk = Storage::disk('local')->exists($payment->proof_path) ? Storage::disk('local') : Storage::disk('minio');
        abort_unless($disk->exists($payment->proof_path), 404);

        return request()->boolean('preview')
            ? $disk->response($payment->proof_path)
            : $disk->download($payment->proof_path);
    }
}
