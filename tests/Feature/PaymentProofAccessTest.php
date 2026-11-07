<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PaymentProofAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_proof_download_requires_billing_permission_even_with_signed_url(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payment-proofs/proof.pdf', 'private proof');
        $payment = Payment::factory()->create(['proof_path' => 'payment-proofs/proof.pdf']);
        $url = URL::temporarySignedRoute('payments.proof', now()->addMinutes(5), ['payment' => $payment->id]);
        $this->actingAs(User::factory()->create(['role' => 'operator']))->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'finance']))->get($url)->assertOk()->assertDownload('proof.pdf');
    }

    public function test_unsigned_download_is_rejected(): void
    {
        $payment = Payment::factory()->create();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('payments.proof', $payment))->assertForbidden();
    }
}
