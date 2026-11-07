<x-filament-panels::page>
 <x-filament::section heading="1. Upload dan mapping CSV">
 <p>Kolom legacy disimpan untuk review. Nilai '-' berarti belum diketahui. Amount tidak dianggap pembayaran; resource tidak otomatis dialokasikan.</p>
 <label>File CSV (maksimum 10 MB, 5000 baris)<input type="file" wire:model="csv" accept=".csv,.txt"></label>
 @error('csv')<p role="alert">{{ $message }}</p>@enderror
 @if($headers)
 @foreach(['name'=>'Nama service','status'=>'Status','reference'=>'No referensi lama','server'=>'Server / provider lama','vcpu'=>'Perkiraan Core','ram_mb'=>'Perkiraan RAM (unit perlu verifikasi)','storage_gb'=>'Perkiraan storage (unit perlu verifikasi)','start_date'=>'Tanggal mulai legacy','end_date'=>'Tanggal akhir legacy','amount'=>'Amount legacy (bukan pembayaran)'] as $key=>$label)
 <label>{{ $label }}<select wire:model="mapping.{{ $key }}"><option value="">Tidak dipetakan</option>@foreach($headers as $header)<option value="{{ $header }}">{{ $header }}</option>@endforeach</select></label>
 @endforeach
 <x-filament::button wire:click="preview" wire:loading.attr="disabled">Preview dan validasi</x-filament::button>
 @endif
 @error('mapping')<p role="alert">{{ $message }}</p>@enderror
 </x-filament::section>
 @if($batch)
 <x-filament::section heading="2. Review pemetaan server dan data legacy">
 <p>Batch #{{ $batch->id }} — {{ $batch->status }}. Verifikasi Core, RAM, Storage, tanggal, dan Amount di data asli. Alokasi dan kontrak dibuat terpisah setelah verifikasi.</p>
 <div style="overflow-x:auto"><table style="width:100%;text-align:left"><thead><tr><th>Baris</th><th>Service / status</th><th>Data asli</th><th>Compute Instance (wajib)</th><th>Validasi</th></tr></thead><tbody>
 @foreach($rows as $row)
 <tr><td>{{ $row->row_number }}</td><td>{{ $row->normalized['name'] }} / {{ $row->normalized['status'] }}</td><td><details><summary>Lihat data</summary><pre>{{ json_encode($row->raw, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details></td><td><select wire:model="instances.{{ $row->id }}" @disabled($batch->status!=='staged')><option value="">Pilih instance</option>@foreach($computeInstances as $id=>$name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></td><td>{{ implode('; ', $row->errors) ?: 'Valid' }}</td></tr>
 @endforeach
 </tbody></table></div>
 {{ $rows->links() }}
 @error('batch')<p role="alert">{{ $message }}</p>@enderror
 @if($batch->status==='staged')<x-filament::button wire:click="confirm" wire:confirm="Simpan seluruh baris valid? Periksa pemetaan dan resource terlebih dahulu." wire:loading.attr="disabled">Konfirmasi import</x-filament::button>@endif
 </x-filament::section>
 @endif
 <x-filament::section heading="Riwayat import">
 @forelse($batches as $item)<p><button type="button" wire:click="resume({{ $item->id }})">Batch #{{ $item->id }} — {{ $item->status }} — {{ $item->created_at }}</button></p>@empty<p>Belum ada import.</p>@endforelse
 </x-filament::section>
</x-filament-panels::page>
