<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
            <tr>
                <th class="px-3 py-2 text-left">Mulai</th>
                <th class="px-3 py-2 text-left">Selesai</th>
                <th class="px-3 py-2 text-right">vCPU</th>
                <th class="px-3 py-2 text-right">RAM (MB)</th>
                <th class="px-3 py-2 text-right">Storage (GB)</th>
                <th class="px-3 py-2 text-left">Oleh</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 text-gray-700 dark:divide-gray-700 dark:text-gray-200">
            @forelse ($allocations as $allocation)
                <tr>
                    <td class="px-3 py-2">{{ $allocation->effective_from?->format('d M Y H:i') }}</td>
                    <td class="px-3 py-2">
                        @if ($allocation->effective_to)
                            {{ $allocation->effective_to->format('d M Y H:i') }}
                        @else
                            <x-filament::badge color="success">Aktif</x-filament::badge>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-right">{{ $allocation->vcpu ?? '—' }}</td>
                    <td class="px-3 py-2 text-right">{{ $allocation->ram_mb ?? '—' }}</td>
                    <td class="px-3 py-2 text-right">{{ $allocation->storage_gb ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $allocation->changedBy?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-3 py-3 text-center">Belum ada alokasi.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
