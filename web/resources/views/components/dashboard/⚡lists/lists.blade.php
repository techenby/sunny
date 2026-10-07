<flux:card body="flush" class="flex flex-col">
    <flux:card.header>
        <flux:card.heading size="lg">{{ __('Open lists') }}</flux:card.heading>
        <flux:card.actions>
            <flux:button size="sm" variant="ghost" icon:trailing="arrow-right" :href="route('lists.index')" wire:navigate>
                {{ __('All lists') }}
            </flux:button>
        </flux:card.actions>
    </flux:card.header>

    <flux:card.body class="flex-1">
        @if ($this->lists->isEmpty())
            <div class="py-6 text-center">
                <flux:icon name="queue-list" class="mx-auto mb-3 size-10 text-zinc-400" />
                <flux:text>{{ __('All caught up. Nothing left on your lists.') }}</flux:text>
            </div>
        @else
            <div class="space-y-5">
                @foreach ($this->lists as $list)
                    <section wire:key="dashboard-list-{{ $list->id }}" class="space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex min-w-0 items-center gap-2">
                                <flux:avatar size="xs" :icon="$list->type->getIcon()" :color="$list->type->getIconColor()" icon:variant="outline" />
                                <flux:link :href="route('lists.show', $list)" wire:navigate variant="ghost" class="truncate">
                                    <flux:heading>{{ $list->name }}</flux:heading>
                                </flux:link>
                            </div>

                            @if ($list->user === null)
                                <flux:badge size="sm" color="zinc">{{ __('Household') }}</flux:badge>
                            @endif
                        </div>

                        <ul>
                            @foreach ($list->items->take($this::ITEMS_PER_LIST) as $item)
                                <li wire:key="dashboard-list-item-{{ $item->id }}">
                                    <button
                                        type="button"
                                        wire:click="toggle({{ $item->id }})"
                                        role="checkbox"
                                        aria-checked="false"
                                        class="-mx-2 flex w-full items-center gap-3 rounded-md px-2 py-1.5 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                    >
                                        <span class="size-5 shrink-0 rounded-full border-2 border-zinc-300 dark:border-zinc-600"></span>
                                        <span class="flex-1 text-sm text-zinc-800 dark:text-zinc-100">{{ $item->name }}</span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        @if ($list->items->count() > $this::ITEMS_PER_LIST)
                            <flux:link :href="route('lists.show', $list)" wire:navigate class="text-sm">
                                {{ __(':count more', ['count' => $list->items->count() - $this::ITEMS_PER_LIST]) }}
                            </flux:link>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </flux:card.body>
</flux:card>
