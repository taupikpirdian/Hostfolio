<x-filament-panels::page>
    @php
        $instance = $this->getRecord()->load(['provider', 'physicalServer', 'parent', 'currentAllocation', 'allocations.changedBy', 'deployments.service', 'billingContracts.party']);
        $deployedServiceIds = $instance->deployments->pluck('service_id')->unique();
        $billingContracts = \App\Models\BillingContract::query()->with(['party', 'invoices'])->where(function ($query) use ($instance, $deployedServiceIds) {
            $query->where('compute_instance_id', $instance->id)->orWhereIn('service_id', $deployedServiceIds);
        })->get();
        $current = $instance->currentAllocation;
        $details = [
            'Code' => $instance->code,
            'Nama' => $instance->name,
            'Tipe' => strtoupper(str_replace('_', ' ', $instance->type)),
            'Status' => ucfirst($instance->status),
            'Provider' => $instance->provider?->name,
            'Physical server' => $instance->physicalServer?->name,
            'Parent instance' => $instance->parent?->name,
            'Hostname' => $instance->hostname,
            'IP address' => $instance->ip_address,
            'Operating system' => $instance->operating_system,
            'External reference' => $instance->external_reference,
            'Notes' => $instance->notes,
        ];
    @endphp

    <div x-data="{ tab: 'overview' }">
    <div class="mb-5 flex gap-2 border-b border-gray-200 dark:border-gray-700">
        <button type="button" @click="tab = 'overview'" :class="tab === 'overview' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-600 dark:text-gray-300'" class="border-b-2 px-4 py-2 text-sm font-semibold">Overview</button>
        <button type="button" @click="tab = 'services'" :class="tab === 'services' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-600 dark:text-gray-300'" class="border-b-2 px-4 py-2 text-sm font-semibold">Services ({{ $instance->deployments->whereNull('removed_at')->count() }})</button>
        <button type="button" @click="tab = 'billing'" :class="tab === 'billing' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-600 dark:text-gray-300'" class="border-b-2 px-4 py-2 text-sm font-semibold">Billing ({{ $billingContracts->count() }})</button>
    </div>

    <div x-show="tab === 'overview'" class="space-y-6">
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 lg:col-span-2">
            <h2 class="mb-4 text-lg font-semibold text-gray-950 dark:text-white">Detail Compute Instance</h2>
            <dl class="grid gap-4 sm:grid-cols-2">
                @foreach ($details as $label => $value)
                    <div>
                        <dt class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="mt-1 break-words font-medium text-gray-900 dark:text-gray-100">{{ filled($value) ? $value : '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h2 class="mb-4 text-lg font-semibold text-gray-950 dark:text-white">Alokasi Aktif</h2>
            <dl class="space-y-4">
                <div><dt class="text-sm text-gray-500 dark:text-gray-400">vCPU</dt><dd class="font-semibold text-gray-900 dark:text-gray-100">{{ $current?->vcpu ?? '—' }}</dd></div>
                <div><dt class="text-sm text-gray-500 dark:text-gray-400">RAM (MB)</dt><dd class="font-semibold text-gray-900 dark:text-gray-100">{{ $current?->ram_mb ?? '—' }}</dd></div>
                <div><dt class="text-sm text-gray-500 dark:text-gray-400">Storage (GB)</dt><dd class="font-semibold text-gray-900 dark:text-gray-100">{{ $current?->storage_gb ?? '—' }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <h2 class="mb-4 text-lg font-semibold text-gray-950 dark:text-white">Riwayat Alokasi</h2>
        @include('filament.resources.compute-instances.allocation-history', ['allocations' => $instance->allocations->sortByDesc('effective_from')])
    </section>
    </div>

    <section x-show="tab === 'billing'" x-cloak class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Billing Contracts</h2>
            <x-filament::actions :actions="$this->billingTabActions()" />
        </div>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead class="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300"><tr><th class="px-3 py-2 text-left">Contract Name</th><th class="px-3 py-2">Direction</th><th class="px-3 py-2 text-left">Client / Vendor</th><th class="px-3 py-2 text-right">Amount</th><th class="px-3 py-2">Cycle</th><th class="px-3 py-2">Next Billing</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Actions</th></tr></thead>
        <tbody class="divide-y divide-gray-200 text-gray-700 dark:divide-gray-700 dark:text-gray-200">
        @forelse ($billingContracts as $contract)
            <tr><td class="px-3 py-2 font-medium">{{ $contract->name }}</td><td class="px-3 py-2">{{ ucfirst($contract->direction) }}</td><td class="px-3 py-2">{{ $contract->party?->name }}</td><td class="px-3 py-2 text-right">{{ $contract->currency }} {{ $contract->amount }}</td><td class="px-3 py-2">{{ ucfirst(str_replace('_', ' ', $contract->billing_cycle)) }}</td><td class="px-3 py-2">{{ $contract->next_billing_date?->format('d M Y') ?? '—' }}</td><td class="px-3 py-2">{{ ucfirst($contract->status) }}</td><td class="px-3 py-2"><button type="button" class="text-primary-600 hover:underline" wire:click="toggleContractInvoices({{ $contract->id }})">{{ $this->expandedBillingContractId === $contract->id ? 'Tutup invoice' : 'Lihat invoice ('.count($contract->invoices).')' }}</button></td></tr>
            @if ($this->expandedBillingContractId === $contract->id)
                <tr><td colspan="8" class="bg-gray-50 p-3 dark:bg-gray-800"><table class="w-full text-sm"><thead><tr><th class="p-2 text-left">Nomor</th><th class="p-2">Periode</th><th class="p-2">Due date</th><th class="p-2 text-right">Total</th><th class="p-2">Status</th><th class="p-2">Status Pembayaran</th><th class="p-2">Bukti</th><th class="p-2">Aksi</th></tr></thead><tbody>
                    @forelse ($contract->invoices->sortByDesc('period_start') as $invoice)
                        <tr>
                            <td class="p-2">{{ $invoice->number }}</td>
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
                                        @if ($invoice->status !== 'void' && ! $invoice->payments->whereNull('reversed_at')->isNotEmpty())
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
                        <tr><td colspan="5" class="p-2 text-center">Belum ada invoice.</td></tr>
                    @endforelse
                </tbody></table></td></tr>
            @endif
        @empty
            <tr><td colspan="8" class="px-3 py-5 text-center">Belum ada billing contract untuk instance ini.</td></tr>
        @endforelse
        </tbody></table></div>
    </section>

    <section x-show="tab === 'services'" x-cloak class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Services pada {{ $instance->name }}</h2>
            <x-filament::actions :actions="$this->serviceTabActions()" />
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    <tr><th class="px-3 py-2 text-left">Service</th><th class="px-3 py-2 text-left">Tipe</th><th class="px-3 py-2 text-left">Environment</th><th class="px-3 py-2 text-left">Status</th><th class="px-3 py-2 text-left">Dipasang</th><th class="px-3 py-2 text-right">Aksi</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-gray-700 dark:divide-gray-700 dark:text-gray-200">
                    @forelse ($instance->deployments->sortByDesc('deployed_at') as $deployment)
                        <tr>
                            <td class="px-3 py-2 font-medium">{{ $deployment->service?->name ?? '—' }}</td>
                            <td class="px-3 py-2">{{ ucfirst(str_replace('_', ' ', $deployment->service?->type ?? '')) }}</td>
                            <td class="px-3 py-2">{{ ucfirst($deployment->environment) }}</td>
                            @php
                                $deploymentStatus = $deployment->removed_at ? 'Selesai' : ucfirst($deployment->service?->status ?? 'Aktif');
                                $statusColors = match (strtolower($deploymentStatus)) {
                                    'selesai', 'stopped' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',
                                    'running', 'aktif' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
                                    'draft', 'planned' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
                                    'archived', 'inactive', 'retired' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                                    default => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                                };
                            @endphp
                            <td class="px-3 py-2"><span class="inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $statusColors }}">{{ $deploymentStatus }}</span></td>
                            <td class="px-3 py-2">{{ $deployment->deployed_at?->format('d M Y H:i') }}</td>
                            <td class="px-3 py-2 text-right">@if (! $deployment->removed_at && auth()->user()?->canManage('inventory'))<button type="button" class="text-sm font-medium text-danger-600 hover:underline" wire:click="removeServiceDeployment({{ $deployment->id }})" wire:confirm="Hapus service ini dari Compute Instance? Histori deployment akan tetap disimpan.">Hapus</button>@endif</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-4 text-center">Belum ada service pada instance ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    </div>

</x-filament-panels::page>
