<div class="space-y-4">
    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $contract->number }} — {{ $contract->name }}</p>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                <tr><th class="p-2 text-left">Nomor</th><th class="p-2">Created At</th><th class="p-2">Periode</th><th class="p-2">Due date</th><th class="p-2 text-right">Total</th><th class="p-2">Status</th><th class="p-2">Status Pembayaran</th><th class="p-2">Bukti</th><th class="p-2">Aksi</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                @forelse ($contract->invoices->sortByDesc('period_start') as $invoice)
                    <tr>
                        <td class="p-2">{{ $invoice->number }}</td>
                        <td class="p-2 text-center whitespace-nowrap">{{ $invoice->created_at?->format('d M Y H:i') ?? '—' }}</td>
                        <td class="p-2 text-center">{{ $invoice->period_start?->format('d M Y') }} — {{ $invoice->period_end?->format('d M Y') }}</td>
                        <td class="p-2 text-center">{{ $invoice->due_date?->format('d M Y') }}</td>
                        <td class="p-2 text-right">{{ $invoice->currency }} {{ $invoice->total }}</td>
                        <td class="p-2 text-center"><x-filament::badge :color="match ($invoice->status) { 'issued' => 'info', 'void' => 'danger', default => 'gray' }">{{ ucfirst($invoice->status) }}</x-filament::badge></td>
                        <td class="p-2 text-center"><x-filament::badge :color="match ($invoice->payment_status) { 'paid' => 'success', 'partial' => 'warning', default => 'danger' }">{{ ucfirst($invoice->payment_status) }}</x-filament::badge></td>
                        <td class="p-2 text-center">@foreach ($invoice->payments->whereNull('reversed_at') as $payment)<a class="text-primary-600 hover:underline" href="{{ app(\App\Services\MinioStorage::class)->temporaryUrl($payment->proof_path) }}" target="_blank">Bukti</a>@endforeach</td>
                        <td class="p-2 text-center">
                            <x-filament::dropdown placement="bottom-end" width="xs">
                                <x-slot name="trigger">
                                    <x-filament::icon-button icon="heroicon-m-ellipsis-vertical" label="Aksi invoice {{ $invoice->number }}" />
                                </x-slot>
                                <x-filament::dropdown.list>
                                    <x-filament::dropdown.list.item tag="a" :href="route('invoices.pdf', $invoice)" target="_blank" icon="heroicon-m-document-arrow-down">Generate Invoice</x-filament::dropdown.list.item>
                                    @if ($invoice->status !== 'void' && $invoice->payments->whereNull('reversed_at')->isEmpty())
                                        <x-filament::dropdown.list.item icon="heroicon-m-x-circle" color="danger" wire:click="cancelInvoice({{ $invoice->id }})" wire:confirm="Batalkan invoice ini?">Batalkan</x-filament::dropdown.list.item>
                                    @endif
                                    @if ($invoice->status === 'issued' && bccomp($invoice->outstanding, '0', 2) > 0)
                                        <x-filament::dropdown.list.item icon="heroicon-m-banknotes" wire:click="mountAction('recordInvoicePayment', { invoice_id: {{ $invoice->id }} })">Bayar</x-filament::dropdown.list.item>
                                    @endif
                                </x-filament::dropdown.list>
                            </x-filament::dropdown>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="p-4 text-center">Belum ada invoice.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
