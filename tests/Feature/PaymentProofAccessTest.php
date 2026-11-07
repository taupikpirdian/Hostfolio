<?php

namespace Tests\Feature;

use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Payment;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentProofAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_minio_s3_disk_can_be_constructed_for_payment_proofs(): void
    {
        config()->set('filesystems.disks.minio.key', 'test-key');
        config()->set('filesystems.disks.minio.secret', 'test-secret');
        config()->set('filesystems.disks.minio.bucket', 'test-proofs');
        config()->set('filesystems.disks.minio.endpoint', 'http://127.0.0.1:9000');
        Storage::purge('minio');

        $this->assertInstanceOf(\Illuminate\Filesystem\AwsS3V3Adapter::class, Storage::disk('minio'));
    }

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

    public function test_payment_detail_shows_proof_without_proof_or_reverse_table_actions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $payment = Payment::factory()->create(['proof_path' => 'payment-proofs/receipt.pdf']);

        Livewire::test(ManagePayments::class)
            ->assertTableActionDoesNotExist('downloadProof')
            ->assertTableActionDoesNotExist('reverse')
            ->mountTableAction('view', $payment)
            ->assertSchemaComponentExists('proof_path', null, fn ($component): bool => str_contains($component->toHtml(), '<iframe'));
    }

    public function test_image_payment_proof_is_previewed_inside_view_modal(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $payment = Payment::factory()->create(['proof_path' => 'payment-proofs/receipt.png']);

        Livewire::test(ManagePayments::class)
            ->mountTableAction('view', $payment)
            ->assertSchemaComponentExists('proof_path', null, fn ($component): bool => str_contains($component->toHtml(), '<img'));
    }

    public function test_signed_payment_proof_preview_reads_local_and_minio_files(): void
    {
        Storage::fake('local');
        Storage::fake('minio');
        $this->actingAs(User::factory()->create(['role' => 'finance']));

        foreach (['local', 'minio'] as $disk) {
            $path = 'payment-proofs/'.$disk.'.pdf';
            Storage::disk($disk)->put($path, '%PDF-1.4 proof');
            $payment = Payment::factory()->create(['proof_path' => $path]);
            $url = URL::temporarySignedRoute('payments.proof', now()->addMinutes(5), ['payment' => $payment->id, 'preview' => 1]);

            $response = $this->get($url)->assertOk();
            $this->assertStringStartsWith('inline;', $response->headers->get('content-disposition'));
            $this->assertSame('%PDF-1.4 proof', $response->streamedContent());
        }
    }
}
