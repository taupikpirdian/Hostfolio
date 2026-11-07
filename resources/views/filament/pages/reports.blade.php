<x-filament-panels::page>
 @if(isset($billingLinks))
 <section class="mb-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
 @foreach($billingLinks as $link)
 <a href="{{ $link['url'] }}" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 px-4 py-3 text-sm font-semibold text-slate-700 dark:text-slate-200 shadow-sm transition hover:border-blue-300 dark:hover:border-blue-700 hover:text-blue-700 dark:hover:text-blue-300">{{ $link['title'] }} <span class="float-right text-slate-400 dark:text-slate-400">&rarr;</span></a>
 @endforeach
 </section>
 @endif
 <x-filament::section heading="Laporan dan renewal">
 <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:end">
 <label>Laporan<x-filament::input.wrapper><x-filament::input.select wire:model.live="report">@foreach($choices as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-filament::input.select></x-filament::input.wrapper></label>
 <label>Dari<x-filament::input.wrapper><x-filament::input type="date" wire:model.live="from"/></x-filament::input.wrapper></label>
 <label>Sampai<x-filament::input.wrapper><x-filament::input type="date" wire:model.live="to"/></x-filament::input.wrapper></label>
 @if($report==='renewals')<label>Renewal<x-filament::input.wrapper><x-filament::input.select wire:model.live="days">@foreach([0=>'Expired',7=>'7 hari',14=>'14 hari',30=>'30 hari',60=>'60 hari'] as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</x-filament::input.select></x-filament::input.wrapper></label>@endif
 <x-filament::button tag="a" href="{{ route('reports.export',['report'=>$report,'from'=>$from,'to'=>$to,'days'=>$days]) }}">Export CSV</x-filament::button>
 </div>
 @error('to')<p role="alert">{{ $message }}</p>@enderror
 <p>Invoice menggunakan tanggal terbit; cashflow menggunakan tanggal pembayaran. Nilai IDR. Alokasi adalah resource provisioned, bukan penggunaan aktual.</p>
 </x-filament::section>
 @if($summary)<x-filament::section heading="Ringkasan billing periode terpilih"><dl style="display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:1rem">@foreach($summary as $label=>$amount)<div><dt>{{ $label }}</dt><dd><strong>Rp {{ number_format((float)$amount,2,',','.') }}</strong></dd></div>@endforeach</dl></x-filament::section>@endif
 <x-filament::section heading="Hasil laporan">
 <div style="overflow-x:auto"><table style="width:100%;text-align:left;border-collapse:collapse">
 @if($rows)<thead><tr>@foreach(array_keys($rows[0]) as $header)<th style="padding:.75rem;border-bottom:1px solid #9ca3af">{{ $header }}</th>@endforeach</tr></thead>@endif
 <tbody>@forelse($rows as $row)<tr>@foreach($row as $value)<td style="padding:.75rem;border-bottom:1px solid #9ca3af">{{ $value ?? 'Belum diketahui' }}</td>@endforeach</tr>@empty<tr><td>Belum ada data untuk filter ini.</td></tr>@endforelse</tbody></table></div>
 <div style="display:flex;gap:1rem;align-items:center;padding-top:1rem"><x-filament::button wire:click="previousPage" :disabled="$pageNumber<=1">Sebelumnya</x-filament::button><span>Halaman {{ $pageNumber }} / {{ max(1,ceil($total/25)) }} · {{ $total }} data</span><x-filament::button wire:click="nextPage" :disabled="$pageNumber*25 >= $total">Berikutnya</x-filament::button></div>
 </x-filament::section>
</x-filament-panels::page>
