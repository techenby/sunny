<div {{ $attributes->class('[--padding:--spacing(2)] [--radius:var(--radius-2xl)] rounded-(--radius) bg-zinc-950/5 p-(--padding) ring-1 ring-zinc-950/5 ring-inset dark:bg-white/5 dark:ring-white/10') }} aria-hidden="true">
    <div class="flex flex-col gap-4 rounded-[calc(var(--radius)-var(--padding))] bg-white p-5 shadow-lg ring-1 ring-zinc-950/10 sm:p-6 dark:bg-zinc-800 dark:shadow-none dark:ring-white/10">
        <div class="max-w-[85%] self-end rounded-2xl rounded-br-md bg-zinc-100 px-4 py-2.5 text-sm text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100">
            Add everything for Sunday chili to the grocery list, but skip what we already have.
        </div>

        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-md bg-zinc-950/5 py-1 pr-2 pl-1 font-mono text-xs text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                    <flux:icon.wrench variant="micro" class="shrink-0" />
                    get-recipe
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-md bg-zinc-950/5 py-1 pr-2 pl-1 font-mono text-xs text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                    <flux:icon.wrench variant="micro" class="shrink-0" />
                    search-items
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-md bg-zinc-950/5 py-1 pr-2 pl-1 font-mono text-xs text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                    <flux:icon.wrench variant="micro" class="shrink-0" />
                    add-checklist-items
                </span>
            </div>
            <p class="text-sm text-zinc-800 dark:text-zinc-200">
                Added 6 items to <span class="font-medium text-zinc-950 dark:text-white">Groceries</span>. I skipped the cumin and canned tomatoes since there are some in the pantry.
            </p>
        </div>

        <div class="max-w-[85%] self-end rounded-2xl rounded-br-md bg-zinc-100 px-4 py-2.5 text-sm text-zinc-900 dark:bg-zinc-700 dark:text-zinc-100">
            Where did the camping stove end up?
        </div>

        <div class="flex flex-col gap-3">
            <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-md bg-zinc-950/5 py-1 pr-2 pl-1 font-mono text-xs text-zinc-600 dark:bg-white/10 dark:text-zinc-300">
                    <flux:icon.wrench variant="micro" class="shrink-0" />
                    search-items
                </span>
            </div>
            <p class="text-sm text-zinc-800 dark:text-zinc-200">
                It's in <span class="font-medium text-zinc-950 dark:text-white">Bin 7 &middot; Camping gear</span> on the top garage shelf, next to the lantern and the spare fuel.
            </p>
        </div>
    </div>
</div>
