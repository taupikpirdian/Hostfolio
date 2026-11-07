@php($user = auth()->user())

<div class="border-t border-slate-100 dark:border-slate-800 p-4">
    <div class="flex items-center justify-between rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 px-3 py-2.5">
        <span class="flex items-center gap-2 text-xs font-medium text-slate-600 dark:text-slate-300">
            <span class="size-2 rounded-full bg-emerald-500"></span>
            Self-hosted Workspace
        </span>
        <x-filament::icon icon="heroicon-o-check" class="size-4 text-emerald-600 dark:text-emerald-400" />
    </div>
    <div class="mt-3 flex items-center gap-3 px-1">
        <span class="flex size-9 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-950 text-xs font-bold text-blue-700 dark:text-blue-300">{{ str($user?->name ?? 'A')->squish()->explode(' ')->map(fn ($part) => mb_substr($part, 0, 1))->take(2)->implode('') }}</span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $user?->name ?? 'Administrator' }}</span>
            <span class="block truncate text-[11px] text-slate-400 dark:text-slate-400">{{ str($user?->role ?? 'admin')->replace('_', ' ')->title() }}</span>
        </span>
    </div>
</div>
