<x-filament-panels::page>
    @php
        $cashflowMaximum = max(1, collect($monthlyCashflow)->max(fn ($month) => max($month['income'], $month['expense'])) ?? 1);
        $monthLabel = $period === 'last_12_months' ? '12 bulan terakhir' : 'Tahun ini';
        $receivable = (float) ($financial['Accounts receivable'] ?? 0);
        $payable = (float) ($financial['Accounts payable'] ?? 0);
        $netCashflow = (float) ($financial['Net cashflow'] ?? 0);
    @endphp

    <div class="space-y-6">
        <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-400">
            <span class="flex items-center gap-1.5"><x-filament::icon icon="heroicon-o-home" class="size-3.5" /> Workspace</span>
            <span aria-hidden="true">/</span>
            <span class="font-semibold text-slate-600 dark:text-slate-300" aria-current="page">Dashboard</span>
        </nav>
        <header class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <p class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                    <x-filament::icon icon="heroicon-o-sun" class="size-4 text-amber-500" />
                    {{ $today }}
                </p>
                <h1 class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Dashboard Overview</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ringkasan infrastruktur, layanan hosting, dan keuangan dalam satu tempat.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <label class="sr-only" for="dashboard-period">Periode</label>
                <select id="dashboard-period" wire:model.live="period" class="min-h-10 rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-700 dark:text-slate-200 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <option value="year">Tahun ini</option>
                    <option value="last_12_months">12 bulan terakhir</option>
                </select>
                @if ($canManageBilling)
                    <a href="{{ $invoiceUrl }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 text-sm font-semibold text-slate-700 dark:text-slate-200 shadow-sm transition hover:bg-slate-50 dark:hover:bg-slate-800">
                        <x-filament::icon icon="heroicon-o-document-text" class="size-4" /> Buat Invoice
                    </a>
                @endif
                @if ($canManageInventory)
                    <a href="{{ $serviceUrl }}" class="inline-flex min-h-10 items-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700">
                        <x-filament::icon icon="heroicon-o-plus" class="size-4" /> Tambah Service
                    </a>
                @endif
            </div>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan">
            @if ($canViewInventory)
                <article class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Active Services</p>
                        <span class="rounded-lg bg-emerald-50 dark:bg-emerald-950 p-2 text-emerald-600 dark:text-emerald-400"><x-filament::icon icon="heroicon-o-globe-alt" class="size-5" /></span>
                    </div>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ number_format($servicesRunning) }}<span class="ml-1 text-base font-medium text-slate-400 dark:text-slate-400">/ {{ number_format($servicesTotal) }}</span></p>
                    <p class="mt-2 flex items-center gap-2 text-xs text-slate-400 dark:text-slate-400"><span class="rounded bg-emerald-50 dark:bg-emerald-950 px-2 py-0.5 font-semibold text-emerald-700 dark:text-emerald-300">Running</span>{{ number_format($servicesArchived) }} service archived</p>
                </article>
                <article class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Physical Servers</p>
                        <span class="rounded-lg bg-blue-50 dark:bg-blue-950 p-2 text-blue-600 dark:text-blue-400"><x-filament::icon icon="heroicon-o-server-stack" class="size-5" /></span>
                    </div>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ number_format($physicalServerCount) }}</p>
                    <p class="mt-2 text-xs text-slate-400 dark:text-slate-400">{{ $physicalServer?->provider?->name ?? 'Belum ada provider' }} · {{ $physicalServer?->location ?? 'Lokasi belum diisi' }}</p>
                </article>
                <article class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Compute Instances</p>
                        <span class="rounded-lg bg-violet-50 dark:bg-violet-950 p-2 text-violet-600 dark:text-violet-400"><x-filament::icon icon="heroicon-o-cpu-chip" class="size-5" /></span>
                    </div>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ number_format($computeInstanceCount) }}</p>
                    <p class="mt-2 text-xs text-slate-400 dark:text-slate-400">{{ number_format((int) ($instanceCounts['vm'] ?? 0) + (int) ($instanceCounts['lxc'] ?? 0)) }} VM / LXC · {{ number_format((int) ($instanceCounts['vps'] ?? 0) + (int) ($instanceCounts['shared_hosting'] ?? 0)) }} eksternal</p>
                </article>
            @endif
            @if ($canViewBilling)
                <article class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <div class="flex items-start justify-between">
                        <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Unpaid Invoices</p>
                        <span class="rounded-lg bg-amber-50 dark:bg-amber-950 p-2 text-amber-600 dark:text-amber-400"><x-filament::icon icon="heroicon-o-document-currency-dollar" class="size-5" /></span>
                    </div>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ number_format($unpaidCount) }}</p>
                    <p class="mt-2 flex items-center gap-2 text-xs text-slate-400 dark:text-slate-400"><span class="rounded bg-amber-50 dark:bg-amber-950 px-2 py-0.5 font-semibold text-amber-700 dark:text-amber-300">Rp{{ number_format($unpaidAmount, 0, ',', '.') }}</span>Outstanding</p>
                </article>
            @endif
        </section>

        @if ($canViewBilling)
            <section>
                <div class="mb-3 flex items-end justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold tracking-tight text-slate-900 dark:text-slate-100">Financial Overview</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Ringkasan berdasarkan transaksi pembayaran</p>
                    </div>
                    <a href="{{ $billingUrl }}" class="text-sm font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300">Lihat detail billing <span aria-hidden="true">→</span></a>
                </div>
                <div class="grid gap-4 xl:grid-cols-[1.65fr_1fr]">
                    <article class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-bold text-slate-900 dark:text-slate-100">Cashflow Overview</h3>
                                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Pemasukan dan pengeluaran per bulan · {{ $monthLabel }}</p>
                            </div>
                            <div class="flex gap-4 text-xs text-slate-500 dark:text-slate-400">
                                <span class="flex items-center gap-1.5"><i class="size-2 rounded-sm bg-blue-600"></i>Income</span>
                                <span class="flex items-center gap-1.5"><i class="size-2 rounded-sm bg-blue-200 dark:bg-blue-700"></i>Expense</span>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap gap-8">
                            <div><p class="text-xs text-slate-400 dark:text-slate-400">Income Received</p><p class="mt-1 text-xl font-bold text-emerald-600 dark:text-emerald-400">Rp{{ number_format((float) ($financial['Income received'] ?? 0), 0, ',', '.') }}</p></div>
                            <div><p class="text-xs text-slate-400 dark:text-slate-400">Expense Paid</p><p class="mt-1 text-xl font-bold text-slate-700 dark:text-slate-200">Rp{{ number_format((float) ($financial['Expense paid'] ?? 0), 0, ',', '.') }}</p></div>
                        </div>
                        <div class="mt-6 grid grid-cols-[2.5rem_1fr] gap-2">
                            <div class="flex h-48 flex-col justify-between pb-6 text-right text-[10px] text-slate-400 dark:text-slate-400">
                                <span>{{ number_format($cashflowMaximum, 0, ',', '.') }}</span>
                                <span>{{ number_format($cashflowMaximum * 0.66, 0, ',', '.') }}</span>
                                <span>{{ number_format($cashflowMaximum * 0.33, 0, ',', '.') }}</span>
                                <span>0</span>
                            </div>
                            <div class="relative flex h-48 items-end justify-between gap-1 border-b border-slate-100 dark:border-slate-800 bg-[repeating-linear-gradient(to_top,transparent_0,transparent_47px,#edf2f7_48px)] dark:bg-[repeating-linear-gradient(to_top,transparent_0,transparent_47px,#334155_48px)] px-1">
                                @forelse ($monthlyCashflow as $month)
                                    <div class="z-10 flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1">
                                        <div class="flex h-[calc(100%-1.5rem)] w-full items-end justify-center gap-1">
                                            <span class="w-2 max-w-[35%] rounded-t-sm bg-blue-600" style="height: {{ $month['income'] > 0 ? min(100, max(1, $month['income'] / $cashflowMaximum * 100)) : 0 }}%" title="Income Rp{{ number_format($month['income'], 0, ',', '.') }}"></span>
                                            <span class="w-2 max-w-[35%] rounded-t-sm bg-blue-200 dark:bg-blue-700" style="height: {{ $month['expense'] > 0 ? min(100, max(1, $month['expense'] / $cashflowMaximum * 100)) : 0 }}%" title="Expense Rp{{ number_format($month['expense'], 0, ',', '.') }}"></span>
                                        </div>
                                        <span class="text-[10px] text-slate-400 dark:text-slate-400">{{ $month['label'] }}</span>
                                    </div>
                                @empty
                                    <p class="absolute inset-0 grid place-items-center text-sm text-slate-400 dark:text-slate-400">Belum ada transaksi pada periode ini.</p>
                                @endforelse
                            </div>
                        </div>
                    </article>

                    <article class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
                        <div class="border-b border-slate-100 dark:border-slate-800 p-5">
                            <h3 class="font-bold text-slate-900 dark:text-slate-100">Billing Summary</h3>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Arus kas dan kewajiban pembayaran</p>
                            <p class="mt-5 text-xs text-slate-400 dark:text-slate-400">Net Cashflow</p>
                            <p class="mt-1 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">Rp{{ number_format($netCashflow, 0, ',', '.') }}</p>
                            <p class="mt-2 inline-flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400"><span class="rounded bg-emerald-50 dark:bg-emerald-950 px-2 py-0.5 font-semibold {{ $netCashflow >= 0 ? 'text-emerald-700 dark:text-emerald-300' : 'text-rose-700 dark:text-rose-300' }}">{{ $netCashflow >= 0 ? 'Positive' : 'Negative' }}</span>Income diterima dikurangi expense dibayar</p>
                        </div>
                        <dl class="space-y-3 p-5 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Accounts Receivable</dt><dd class="font-semibold text-amber-600 dark:text-amber-400">Rp{{ number_format($receivable, 0, ',', '.') }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Accounts Payable</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">Rp{{ number_format($payable, 0, ',', '.') }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Active Billing Contracts</dt><dd class="font-semibold text-slate-700 dark:text-slate-200">{{ number_format($activeContractCount) }} contracts</dd></div>
                        </dl>
                        <a href="{{ $billingUrl }}" class="mx-4 mb-4 flex items-center justify-between rounded-lg bg-slate-50 dark:bg-slate-800 px-4 py-3 text-xs font-semibold text-blue-700 dark:text-blue-300 hover:bg-blue-50 dark:hover:bg-blue-950">Kelola invoice & pembayaran <span>→</span></a>
                    </article>
                </div>
            </section>
        @endif

        <section class="grid gap-4 xl:grid-cols-[1.25fr_1fr]">
            @if ($canViewBilling)
                <article class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
                    <div class="flex items-start justify-between gap-3 p-5">
                        <div><h2 class="font-bold text-slate-900 dark:text-slate-100">Invoice Belum Dibayar</h2><p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Tagihan yang masih memiliki pembayaran tertunda</p></div>
                        <span class="rounded-md bg-slate-100 dark:bg-slate-800 px-2 py-1 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ number_format($unpaidCount) }} invoice</span>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800 px-5">
                        @forelse ($unpaidInvoices as $invoice)
                            <div class="flex items-center gap-3 py-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-950 text-amber-600 dark:text-amber-400"><x-filament::icon icon="heroicon-o-document-text" class="size-4" /></span>
                                <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $invoice->number }}</p><p class="truncate text-xs text-slate-400 dark:text-slate-400">{{ $invoice->party?->name ?? 'Tanpa pihak' }} · Jatuh tempo {{ $invoice->due_date->locale('id')->translatedFormat('d M Y') }}</p></div>
                                <div class="shrink-0 text-right"><p class="text-xs font-semibold text-slate-700 dark:text-slate-200">Rp{{ number_format((float) $invoice->outstanding, 0, ',', '.') }}</p><p class="text-xs text-slate-400 dark:text-slate-400">{{ $invoice->payment_status === 'partial' ? 'Sebagian dibayar' : 'Belum dibayar' }}</p></div>
                            </div>
                        @empty
                            <p class="py-8 text-center text-sm text-slate-400 dark:text-slate-400">Tidak ada invoice yang belum dibayar.</p>
                        @endforelse
                    </div>
                    <a href="{{ $invoiceUrl }}" class="flex items-center justify-between border-t border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/70 px-5 py-3 text-xs font-semibold text-blue-600 dark:text-blue-400 hover:bg-slate-100 dark:hover:bg-slate-800">Lihat semua invoice <span aria-hidden="true">→</span></a>
                </article>
            @endif

            @if ($canViewInventory)
                <article class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div><h2 class="font-bold text-slate-900 dark:text-slate-100">Infrastructure Inventory</h2><p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Rangkuman aset dan penempatan layanan</p></div>
                        <a href="{{ $serversUrl }}" class="shrink-0 text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300">Lihat servers <span>→</span></a>
                    </div>
                    @if ($physicalServer)
                        <div class="mt-4 flex items-center gap-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-800/80 p-3">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300"><x-filament::icon icon="heroicon-o-server-stack" class="size-5" /></span>
                            <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $physicalServer->name }}</p><p class="text-xs text-slate-400 dark:text-slate-400">{{ $physicalServer->provider?->name ?? 'Provider belum diisi' }} · {{ $physicalServer->location ?? 'On-premise' }}</p></div>
                            <span class="rounded bg-emerald-50 dark:bg-emerald-950 px-2 py-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">Active</span>
                        </div>
                    @else
                        <div class="mt-4 rounded-xl border border-dashed border-slate-200 dark:border-slate-700 p-5 text-center text-sm text-slate-400 dark:text-slate-400">Belum ada physical server aktif.</div>
                    @endif
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3"><p class="text-[11px] text-slate-400 dark:text-slate-400">Physical Host</p><p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-200">{{ number_format($physicalServerCount) }}</p><p class="text-[10px] text-slate-400 dark:text-slate-400">Server fisik</p></div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3"><p class="text-[11px] text-slate-400 dark:text-slate-400">VM / LXC</p><p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-200">{{ number_format((int) ($instanceCounts['vm'] ?? 0) + (int) ($instanceCounts['lxc'] ?? 0)) }}</p><p class="text-[10px] text-slate-400 dark:text-slate-400">Active instance</p></div>
                        <div class="rounded-lg border border-slate-200 dark:border-slate-700 p-3"><p class="text-[11px] text-slate-400 dark:text-slate-400">External</p><p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-200">{{ number_format((int) ($instanceCounts['vps'] ?? 0) + (int) ($instanceCounts['shared_hosting'] ?? 0)) }}</p><p class="text-[10px] text-slate-400 dark:text-slate-400">VPS / Hosting</p></div>
                    </div>
                    <div class="mt-3 flex items-start gap-2 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950 p-3 text-xs text-amber-800 dark:text-amber-200"><x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 size-4 shrink-0" /><p>Pastikan setiap alokasi hanya dipetakan ke instance induk agar kapasitas tidak dihitung ganda.</p></div>
                </article>
            @endif
        </section>

        @if ($canViewInventory)
            <section class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 shadow-sm">
                <div class="flex flex-col gap-3 border-b border-slate-100 dark:border-slate-800 p-5 lg:flex-row lg:items-center lg:justify-between">
                    <div><h2 class="font-bold text-slate-900 dark:text-slate-100">Hosted Services</h2><p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Daftar aplikasi terdaftar beserta instance dan masa aktifnya</p></div>
                    <div class="flex flex-wrap gap-2">
                        <label class="relative"><span class="sr-only">Cari service</span><x-filament::icon icon="heroicon-o-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-slate-400 dark:text-slate-400" /><input type="search" wire:model.live.debounce.300ms="serviceSearch" placeholder="Cari service..." class="bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 placeholder:text-slate-400 min-h-9 w-56 rounded-lg border-slate-200 dark:border-slate-700 pl-9 text-sm focus:border-blue-500 focus:ring-blue-500"></label>
                        <label><span class="sr-only">Filter status</span><select wire:model.live="serviceStatus" class="min-h-9 rounded-lg border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-sm text-slate-600 dark:text-slate-300 focus:border-blue-500 focus:ring-blue-500"><option value="all">All Status</option><option value="running">Running</option><option value="stopped">Stopped</option><option value="draft">Draft</option><option value="archived">Archived</option></select></label>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-left text-sm">
                        <thead class="bg-slate-50 dark:bg-slate-800 text-[11px] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-400"><tr><th class="px-5 py-3">Service</th><th class="px-4 py-3">Instance</th><th class="px-4 py-3">Resource</th><th class="px-4 py-3">Status</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($services as $service)
                                @php
                                    $deployment = $service->deployments->firstWhere('is_primary', true) ?? $service->deployments->first();
                                    $instance = $deployment?->instance;
                                    $allocation = $instance?->currentAllocation;
                                    $legacy = $service->legacy_data ?? [];
                                    $vcpu = $allocation?->vcpu ?? ($legacy['vcpu'] ?? null);
                                    $ram = $allocation?->ram_mb ?? ($legacy['ram_mb'] ?? null);
                                    $ramGb = $ram === null ? null : rtrim(rtrim(number_format((float) $ram / 1024, 2, ',', '.'), '0'), ',');
                                @endphp
                                <tr class="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/70">
                                    <td class="px-5 py-3"><div class="flex items-center gap-3"><span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400"><x-filament::icon icon="heroicon-o-globe-alt" class="size-4" /></span><div><p class="font-semibold text-slate-800 dark:text-slate-200">{{ $service->name }}</p><p class="text-xs text-slate-400 dark:text-slate-400">{{ str($service->type)->replace('_', ' ')->title() }}</p></div></div></td>
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">
                                        @if ($instance)
                                            <a href="{{ \App\Filament\Resources\ComputeInstances\ComputeInstanceResource::getUrl('view', ['record' => $instance]) }}" class="font-medium text-blue-600 hover:underline dark:text-blue-400">{{ $instance->name }}</a>
                                        @else
                                            Belum dipetakan
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $vcpu !== null || $ram !== null ? ($vcpu ?? '?').' vCPU · '.($ramGb ?? '?').' GB RAM' : '—' }}<span class="block text-xs text-slate-400 dark:text-slate-400">{{ $allocation ? 'Alokasi terstruktur' : 'Estimasi dari Sheet' }}</span></td>
                                    <td class="px-4 py-3"><span @class(['rounded-full px-2 py-1 text-xs font-semibold', 'bg-emerald-50 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300' => $service->status === 'running', 'bg-amber-50 dark:bg-amber-950 text-amber-700 dark:text-amber-300' => $service->status === 'draft', 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' => in_array($service->status, ['stopped', 'archived'], true)])>{{ str($service->status)->ucfirst() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-12 text-center text-sm text-slate-400 dark:text-slate-400">Tidak ada service yang cocok dengan pencarian ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 dark:border-slate-800 px-5 py-3 text-xs text-slate-400 dark:text-slate-400">
                    <span>Menampilkan {{ $services->firstItem() ?? 0 }}–{{ $services->lastItem() ?? 0 }} dari {{ number_format($services->total()) }} service</span>
                    <div class="flex gap-2">
                        <button type="button" wire:click="previousPage" @disabled($services->onFirstPage()) class="rounded-md border border-slate-200 dark:border-slate-700 p-1.5 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-40" aria-label="Halaman sebelumnya"><x-filament::icon icon="heroicon-o-chevron-left" class="size-4" /></button>
                        <button type="button" wire:click="nextPage" @disabled(! $services->hasMorePages()) class="rounded-md border border-slate-200 dark:border-slate-700 p-1.5 text-slate-500 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800 disabled:opacity-40" aria-label="Halaman selanjutnya"><x-filament::icon icon="heroicon-o-chevron-right" class="size-4" /></button>
                    </div>
                </div>
            </section>
        @endif

        <footer class="flex items-center justify-between text-xs text-slate-400 dark:text-slate-400"><span>Hostfolio v1.0 · Inventory & Billing</span><span>Self-hosted Workspace</span></footer>
    </div>
</x-filament-panels::page>
