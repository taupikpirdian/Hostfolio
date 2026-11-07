<span class="flex items-center gap-3">
    <span class="flex size-10 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-600/25">
        <svg viewBox="0 0 24 24" fill="none" class="size-6" aria-hidden="true">
            <rect x="4" y="4" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.8" />
            <rect x="14" y="4" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.8" />
            <rect x="4" y="14" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.8" />
            <rect x="14" y="14" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.8" />
        </svg>
    </span>
    <span class="flex flex-col leading-tight">
        <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ str(filament()->getBrandName())->finish('.') }}</span>
        <span class="text-[9px] font-semibold tracking-[0.12em] text-slate-400 dark:text-slate-400">INVENTORY &amp; BILLING</span>
    </span>
</span>
