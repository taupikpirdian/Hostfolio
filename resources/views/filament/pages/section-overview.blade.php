<x-filament-panels::page>
    <div class="space-y-6">
        <header class="space-y-1">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ $title }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $description }}</p>
        </header>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($stats as $stat)
                <article class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm">
                    <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $stat['label'] }}</p>
                    <p class="mt-2 text-3xl font-bold tracking-tight text-slate-900 dark:text-slate-100">{{ $stat['value'] }}</p>
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-400">{{ $stat['detail'] }}</p>
                </article>
            @endforeach
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" class="group flex items-start gap-4 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 p-5 shadow-sm transition hover:border-blue-300 dark:hover:border-blue-700 hover:shadow-md">
                    <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-950 text-blue-600 dark:text-blue-400">
                        <x-filament::icon :icon="$link['icon']" class="size-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold text-slate-900 dark:text-slate-100 group-hover:text-blue-700 dark:group-hover:text-blue-300">{{ $link['title'] }}</span>
                        <span class="mt-1 block text-sm text-slate-500 dark:text-slate-400">{{ $link['description'] }}</span>
                    </span>
                    <span class="text-slate-400 dark:text-slate-400 transition group-hover:translate-x-1 group-hover:text-blue-600 dark:group-hover:text-blue-400">&rarr;</span>
                </a>
            @endforeach
        </section>
    </div>
</x-filament-panels::page>
