@props(['size' => null])

@php
    $path = [
        ['name' => 'Garage', 'type' => 'Location', 'icon' => 'map-pin', 'color' => 'bg-red-200 text-red-800'],
        ['name' => 'Shelf 2', 'type' => 'Location', 'icon' => 'map-pin', 'color' => 'bg-red-200 text-red-800'],
    ];

    $contents = [
        ['name' => 'Icicle lights', 'type' => 'Item'],
        ['name' => 'Extension cords', 'type' => 'Item'],
        ['name' => 'Tree topper', 'type' => 'Item'],
    ];
@endphp

<div {{ $attributes->class([
    'rounded-(--radius) bg-zinc-900 p-(--padding) shadow-2xl ring-1 ring-zinc-950/10 dark:bg-zinc-950 dark:shadow-none dark:ring-white/10',
    '[--padding:--spacing(1.5)] [--radius:--spacing(7)]' => $size === 'sm',
    '[--padding:--spacing(2.5)] [--radius:--spacing(12)]' => $size !== 'sm',
]) }}>
    <div class="overflow-hidden rounded-[calc(var(--radius)-var(--padding))]">
        <x-welcome.screen width="390" height="844">
            <div class="flex h-full flex-col bg-(--theme-background) font-[-apple-system,BlinkMacSystemFont,system-ui,sans-serif] text-(--theme-on-surface) [--theme-background:#FAF1EF] [--theme-on-surface-variant:#5D5350] [--theme-on-surface:#0B0808] [--theme-primary:#E85A48] [--theme-surface:#FEF9F7] dark:[--theme-background:#0C0807] dark:[--theme-on-surface-variant:#A89B98] dark:[--theme-on-surface:#FEF9F7] dark:[--theme-primary:#F87966] dark:[--theme-surface:#1A1413]">
                <div class="flex h-14 shrink-0 items-center justify-between px-8 pt-3 text-base font-semibold">
                    <span class="tabular-nums">9:41</span>
                    <span class="h-8 w-28 rounded-full bg-black"></span>
                    <flux:icon.signal-slash variant="mini" />
                </div>

                <div class="flex items-center justify-between px-4 py-1.5">
                    <span class="flex size-11 items-center justify-center rounded-full bg-(--theme-surface)/80 ring-1 ring-black/5 dark:ring-white/10">
                        <flux:icon.chevron-left />
                    </span>
                    <span class="flex h-11 items-center gap-5 rounded-full bg-(--theme-surface)/80 px-4 ring-1 ring-black/5 dark:ring-white/10">
                        <flux:icon.pencil variant="outline" />
                        <flux:icon.trash variant="outline" />
                    </span>
                </div>

                <div class="flex flex-col gap-6 px-4 pt-2">
                    <div class="flex flex-col gap-3">
                        <p class="text-[2.125rem] font-bold tracking-tight">Holiday lights</p>

                        <div class="flex items-center gap-2">
                            <span class="flex size-6 items-center justify-center rounded-sm bg-orange-200 text-orange-800">
                                <flux:icon.archive-box variant="micro" />
                            </span>
                            <p class="flex-1 text-base text-(--theme-on-surface-variant)">Bin &middot; in Shelf 2</p>
                        </div>

                        <div class="flex items-center gap-2 text-(--theme-on-surface-variant)">
                            <flux:icon.cloud-arrow-up variant="micro" class="shrink-0" />
                            <p class="flex-1 text-sm">Saved on this phone &middot; syncing with Sunny</p>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3">
                        <p class="text-lg font-medium">Where it is</p>
                        <div class="rounded-[18px] border border-(--theme-on-surface)/10 bg-(--theme-surface)">
                            @foreach ($path as $ancestor)
                                <div @class(['flex items-center gap-3 py-3 pr-4', $loop->index ? 'pl-8' : 'pl-4'])>
                                    <span class="flex size-8 shrink-0 items-center justify-center rounded-md {{ $ancestor['color'] }}">
                                        <flux:icon :icon="$ancestor['icon']" variant="mini" />
                                    </span>
                                    <div class="flex flex-1 flex-col gap-0.5">
                                        <p class="text-base font-medium">{{ $ancestor['name'] }}</p>
                                        <p class="text-sm text-(--theme-on-surface-variant)">{{ $ancestor['type'] }}</p>
                                    </div>
                                    <flux:icon.chevron-right variant="micro" class="text-(--theme-on-surface-variant)" />
                                </div>
                                @unless ($loop->last)
                                    <div class="ml-4 border-t border-(--theme-on-surface)/10"></div>
                                @endunless
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-col gap-3">
                        <div class="flex items-center">
                            <p class="flex-1 text-lg font-medium">Contents</p>
                            <p class="text-sm text-(--theme-on-surface-variant)">{{ count($contents) }} items</p>
                        </div>
                        <div class="rounded-[18px] border border-(--theme-on-surface)/10 bg-(--theme-surface)">
                            @foreach ($contents as $child)
                                <div class="flex items-center gap-4 px-4 py-3">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-amber-200 text-amber-800">
                                        <flux:icon.cube />
                                    </span>
                                    <div class="flex flex-1 flex-col gap-0.5">
                                        <p class="text-base font-medium">{{ $child['name'] }}</p>
                                        <p class="text-sm text-(--theme-on-surface-variant)">{{ $child['type'] }}</p>
                                    </div>
                                    <flux:icon.chevron-right variant="micro" class="text-(--theme-on-surface-variant)" />
                                </div>
                                <div class="ml-18 border-t border-(--theme-on-surface)/10"></div>
                            @endforeach
                            <div class="flex items-center gap-4 px-4 py-3">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-(--theme-primary)/15 text-(--theme-primary)">
                                    <flux:icon.plus />
                                </span>
                                <p class="flex-1 text-base font-medium text-(--theme-primary)">Add item here</p>
                            </div>
                            <div class="ml-18 border-t border-(--theme-on-surface)/10"></div>
                            <div class="flex items-center gap-4 px-4 py-3">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-(--theme-primary)/15 text-(--theme-primary)">
                                    <flux:icon.qr-code />
                                </span>
                                <p class="flex-1 text-base font-medium text-(--theme-primary)">Scan items here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </x-welcome.screen>
    </div>
</div>
