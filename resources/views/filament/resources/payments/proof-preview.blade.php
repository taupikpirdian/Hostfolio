@php
    $extension = strtolower(pathinfo($payment->proof_path, PATHINFO_EXTENSION));
@endphp

<div class="space-y-2">
    <p class="text-sm font-medium text-gray-950 dark:text-white">Bukti pembayaran</p>
    @if (in_array($extension, ['jpg', 'jpeg', 'png'], true))
        <img src="{{ $previewUrl }}" alt="Bukti pembayaran {{ $payment->invoice?->number }}" class="max-h-[32rem] w-full rounded-lg border border-gray-200 object-contain dark:border-gray-700" loading="lazy">
    @elseif ($extension === 'pdf')
        <iframe src="{{ $previewUrl }}" title="Bukti pembayaran {{ $payment->invoice?->number }}" class="h-[32rem] w-full rounded-lg border border-gray-200 dark:border-gray-700" loading="lazy"></iframe>
    @else
        <a href="{{ $previewUrl }}" target="_blank" rel="noopener noreferrer" class="text-primary-600 hover:underline dark:text-primary-400">Buka bukti pembayaran</a>
    @endif
</div>
