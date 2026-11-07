<x-filament-panels::page>
    @php
        $money = fn ($value) => 'Rp '.number_format((float) $value, 2, ',', '.');
        $summaryIcons = [
            'Active contract face value (mixed cycles)' => 'heroicon-o-receipt-percent',
            'Total invoiced income' => 'heroicon-o-chart-bar',
            'Total billed expense' => 'heroicon-o-document-text',
            'Income received' => 'heroicon-o-arrow-down-left',
            'Expense paid' => 'heroicon-o-credit-card',
            'Accounts receivable' => 'heroicon-o-square-3-stack-3d',
            'Accounts payable' => 'heroicon-o-square-3-stack-3d',
            'Net cashflow' => 'heroicon-o-arrow-trending-up',
        ];
        $summaryLabels = [
            'Active contract face value (mixed cycles)' => 'Nilai kontrak aktif',
            'Total invoiced income' => 'Total invoice pemasukan',
            'Total billed expense' => 'Total tagihan pengeluaran',
            'Income received' => 'Pembayaran diterima',
            'Expense paid' => 'Pengeluaran dibayar',
            'Accounts receivable' => 'Piutang',
            'Accounts payable' => 'Utang',
            'Net cashflow' => 'Arus kas bersih',
        ];
        $chartMax = max(1, ...array_map(fn ($month) => max($month['income'], $month['expense']), $cashflowMonths));
        $chartCount = count($cashflowMonths);
        $chartX = fn ($index) => $chartCount === 1 ? 570 : 20 + $index * 550 / ($chartCount - 1);
        $chartY = fn ($value) => 180 - ($value / $chartMax) * 150;
        $incomePoints = collect($cashflowMonths)->map(fn ($month, $index) => round($chartX($index), 1).','.round($chartY($month['income']), 1))->implode(' ');
        $expensePoints = collect($cashflowMonths)->map(fn ($month, $index) => round($chartX($index), 1).','.round($chartY($month['expense']), 1))->implode(' ');
        $incomeArea = ($chartCount === 1 ? '20,180 ' : '20,180 ').$incomePoints.' 570,180';
        $incomeTotal = array_sum(array_column($cashflowMonths, 'income'));
        $expenseTotal = array_sum(array_column($cashflowMonths, 'expense'));
        $pageCount = max(1, (int) ceil($total / 25));
    @endphp

    <div class="space-y-4 text-slate-800 dark:text-slate-100">
        <p class="-mt-3 text-sm text-slate-500 dark:text-slate-400">Kelola kontrak, invoice, pembayaran, dan renewal.</p>

        <nav aria-label="Menu Billing" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            @foreach($billingLinks as $link)
                <a href="{{ $link['url'] }}" class="group flex min-w-0 items-center gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-blue-400 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-blue-500">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400"><x-filament::icon :icon="$link['icon']" class="size-5" /></span>
                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold">{{ $link['title'] }}</span><span class="block truncate text-xs text-slate-500 dark:text-slate-400">{{ $link['subtitle'] }}</span></span>
                    <x-filament::icon icon="heroicon-o-arrow-right" class="size-4 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-blue-500" />
                </a>
            @endforeach
        </nav>

        <section aria-label="Ringkasan Billing" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-blue-400 bg-white p-4 shadow-sm ring-1 ring-blue-400/30 dark:bg-gradient-to-br dark:from-blue-950/70 dark:to-slate-900">
                <div class="flex items-start gap-3"><span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-500/20 dark:text-blue-400"><x-filament::icon icon="heroicon-o-document-text" class="size-6" /></span><div><p class="text-xs text-slate-500 dark:text-slate-300">Total Invoiced Income</p><p class="mt-1 text-xl font-bold tracking-tight">{{ $money($summary['Total invoiced income'] ?? 0) }}</p></div></div>
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Invoice pemasukan pada periode terpilih</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start gap-3"><span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400"><x-filament::icon icon="heroicon-o-square-3-stack-3d" class="size-6" /></span><div><p class="text-xs text-slate-500 dark:text-slate-300">Outstanding Receivable</p><p class="mt-1 text-xl font-bold tracking-tight">{{ $money($summary['Accounts receivable'] ?? 0) }}</p></div></div>
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">{{ $unpaidInvoiceCount }} invoice pemasukan belum lunas</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start gap-3"><span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400"><x-filament::icon icon="heroicon-o-check" class="size-6" /></span><div><p class="text-xs text-slate-500 dark:text-slate-300">Paid This Period</p><p class="mt-1 text-xl font-bold tracking-tight">{{ $money($summary['Income received'] ?? 0) }}</p></div></div>
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Pembayaran masuk pada periode terpilih</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start gap-3"><span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400"><x-filament::icon icon="heroicon-o-calendar-days" class="size-6" /></span><div><p class="text-xs text-slate-500 dark:text-slate-300">Upcoming Renewals</p><p class="mt-1 text-xl font-bold tracking-tight">{{ $upcomingRenewals }}</p></div></div>
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Kontrak jatuh tempo dalam 30 hari</p>
            </div>
        </section>

        <section aria-labelledby="billing-report-filters" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex min-w-56 flex-1 items-center gap-3 border-slate-200 dark:border-slate-700 lg:border-r lg:pr-4"><span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400"><x-filament::icon icon="heroicon-o-chart-bar" class="size-5" /></span><div><h2 id="billing-report-filters" class="font-semibold">Laporan dan Renewal</h2><p class="text-xs text-slate-500 dark:text-slate-400">Buat laporan invoice, cashflow, dan renewal.</p></div></div>
                <label class="min-w-44 flex-1 text-xs font-medium">Laporan<x-filament::input.wrapper class="mt-1"><x-filament::input.select wire:model.live="report">@foreach($choices as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-filament::input.select></x-filament::input.wrapper></label>
                <label class="min-w-36 flex-1 text-xs font-medium">Dari<x-filament::input.wrapper class="mt-1"><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper></label>
                <label class="min-w-36 flex-1 text-xs font-medium">Sampai<x-filament::input.wrapper class="mt-1"><x-filament::input type="date" wire:model.live="to" /></x-filament::input.wrapper></label>
                @if($report === 'renewals')<label class="min-w-28 text-xs font-medium">Renewal<x-filament::input.wrapper class="mt-1"><x-filament::input.select wire:model.live="days">@foreach([0 => 'Expired', 7 => '7 hari', 14 => '14 hari', 30 => '30 hari', 60 => '60 hari'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-filament::input.select></x-filament::input.wrapper></label>@endif
                <a href="{{ route('reports.export', ['report' => $report, 'from' => $from, 'to' => $to, 'days' => $days]) }}" class="inline-flex h-9 items-center gap-2 rounded-lg bg-blue-600 px-4 text-sm font-semibold text-white transition hover:bg-blue-700"><x-filament::icon icon="heroicon-o-arrow-down-tray" class="size-4" />Export CSV</a>
            </div>
            @error('to')<p role="alert" class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </section>

        <section aria-labelledby="billing-report-summary" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900">
            <div class="mb-4 flex items-center gap-3"><span class="flex size-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400"><x-filament::icon icon="heroicon-o-chart-bar" class="size-5" /></span><div><h2 id="billing-report-summary" class="text-lg font-bold">Laporan Billing</h2><p class="text-xs text-slate-500 dark:text-slate-400">Ringkasan, tren, dan hasil laporan invoice.</p></div></div>
            <div class="grid gap-4 xl:grid-cols-2">
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"><h3 class="mb-2 text-sm font-semibold">Ringkasan Periode Terpilih</h3><dl class="grid gap-x-5 sm:grid-cols-2">
                    @foreach($summary as $label => $amount)
                        <div class="flex items-center gap-3 border-t border-slate-100 py-2 dark:border-slate-800"><span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400"><x-filament::icon :icon="$summaryIcons[$label] ?? 'heroicon-o-chart-bar'" class="size-4" /></span><div class="min-w-0"><dt class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $summaryLabels[$label] ?? $label }}</dt><dd class="text-sm font-semibold">{{ $money($amount) }}</dd></div></div>
                    @endforeach
                </dl></div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-700"><div class="flex flex-wrap items-center justify-between gap-2"><h3 class="text-sm font-semibold">Tren Cashflow (Income vs Expense)</h3><span class="rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400">{{ $chartFrom->locale('id')->translatedFormat('d M Y') }} – {{ $chartTo->locale('id')->translatedFormat('d M Y') }}</span></div>
                    <div class="mt-3 grid grid-cols-[auto_1fr] gap-2"><div class="flex flex-col justify-between py-1 text-right text-[10px] text-slate-500 dark:text-slate-400"><span>{{ $money($chartMax) }}</span><span>{{ $money($chartMax / 2) }}</span><span>Rp 0</span></div><div class="min-w-0"><svg viewBox="0 0 600 200" preserveAspectRatio="none" class="h-44 w-full" role="img" aria-label="Tren pembayaran masuk dan keluar bulanan"><defs><linearGradient id="billing-income-fill" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#3b82f6" stop-opacity=".4" /><stop offset="100%" stop-color="#3b82f6" stop-opacity="0" /></linearGradient></defs><path d="M20 30H570 M20 105H570 M20 180H570" stroke="currentColor" class="text-slate-200 dark:text-slate-700" stroke-dasharray="3 4" fill="none" /><polygon points="{{ $incomeArea }}" fill="url(#billing-income-fill)" /><polyline points="{{ $incomePoints }}" fill="none" stroke="#3b82f6" stroke-width="3" stroke-linejoin="round" /><polyline points="{{ $expensePoints }}" fill="none" stroke="#a855f7" stroke-width="3" stroke-linejoin="round" />@foreach($cashflowMonths as $index => $month)<circle cx="{{ $chartX($index) }}" cy="{{ $chartY($month['income']) }}" r="3.5" fill="#3b82f6"><title>{{ $month['label'] }}: pemasukan {{ $money($month['income']) }}</title></circle><circle cx="{{ $chartX($index) }}" cy="{{ $chartY($month['expense']) }}" r="3.5" fill="#a855f7"><title>{{ $month['label'] }}: pengeluaran {{ $money($month['expense']) }}</title></circle>@endforeach</svg><div class="flex justify-between text-[10px] text-slate-500 dark:text-slate-400"><span>{{ $cashflowMonths[0]['label'] }}</span><span>{{ $cashflowMonths[$chartCount - 1]['label'] }}</span></div></div></div>
                    <div class="mt-3 flex flex-wrap justify-center gap-x-6 gap-y-1 text-xs"><span><i class="mr-1 inline-block size-2 rounded-full bg-blue-500"></i>Income (pembayaran) <strong>{{ $money($incomeTotal) }}</strong></span><span><i class="mr-1 inline-block size-2 rounded-full bg-purple-500"></i>Expense (pembayaran) <strong>{{ $money($expenseTotal) }}</strong></span></div>
                </div>
            </div>
        </section>

        <section aria-labelledby="billing-report-results" class="rounded-xl border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900"><h2 id="billing-report-results" class="mb-3 flex items-center gap-2 px-2 text-lg font-bold"><x-filament::icon icon="heroicon-o-list-bullet" class="size-5 text-blue-500" />Hasil Laporan</h2>
            <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700"><table class="w-full min-w-max text-left text-sm"><thead class="bg-slate-50 text-xs dark:bg-slate-800">@if($rows)<tr>@foreach(array_keys($rows[0]) as $header)<th scope="col" class="px-4 py-3 font-semibold">{{ $header }}</th>@endforeach</tr>@endif</thead><tbody class="divide-y divide-slate-100 dark:divide-slate-800">@forelse($rows as $row)<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50">@foreach($row as $header => $value)<td class="px-4 py-3 {{ $header === 'Invoice' ? 'font-semibold' : '' }}">@if(in_array($header, ['Payment status', 'Status', 'Overdue', 'Type']))<span @class(['rounded-full px-2.5 py-1 text-xs font-medium', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => in_array($value, ['paid', 'no', 'upcoming', 'receivable']), 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => in_array($value, ['unpaid', 'partial', 'yes', 'expired']), 'bg-slate-100 text-slate-600 dark:bg-slate-700 dark:text-slate-300' => ! in_array($value, ['paid', 'no', 'upcoming', 'receivable', 'unpaid', 'partial', 'yes', 'expired'])])>{{ match($value) { 'receivable' => 'Pemasukan', 'payable' => 'Pengeluaran', 'paid' => 'Lunas', 'unpaid' => 'Belum lunas', 'partial' => 'Sebagian', 'yes' => 'Ya', 'no' => 'Tidak', 'upcoming' => 'Mendatang', 'expired' => 'Lewat', default => $value ?? '—' } }}</span>@elseif(str_ends_with($header, 'IDR')){{ $money($value) }}@else{{ $value ?? '—' }}@endif</td>@endforeach</tr>@empty<tr><td class="px-4 py-8 text-center text-slate-500 dark:text-slate-400">Belum ada data untuk filter ini.</td></tr>@endforelse</tbody></table></div>
            <div class="flex flex-wrap items-center justify-between gap-3 px-2 pt-3 text-xs text-slate-500 dark:text-slate-400"><span>Menampilkan {{ $total ? (($pageNumber - 1) * 25 + 1) : 0 }}–{{ min($pageNumber * 25, $total) }} dari {{ $total }} data</span><div class="flex items-center gap-2"><button type="button" wire:click="previousPage" @disabled($pageNumber <= 1) class="rounded-lg border border-slate-200 px-3 py-1.5 hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:hover:bg-slate-800">Sebelumnya</button><span class="rounded-lg bg-blue-600 px-3 py-1.5 font-semibold text-white">{{ $pageNumber }} / {{ $pageCount }}</span><button type="button" wire:click="nextPage" @disabled($pageNumber >= $pageCount) class="rounded-lg border border-slate-200 px-3 py-1.5 hover:bg-slate-50 disabled:opacity-40 dark:border-slate-700 dark:hover:bg-slate-800">Berikutnya</button></div></div>
        </section>
    </div>
</x-filament-panels::page>
