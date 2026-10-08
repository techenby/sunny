<flux:card body="flush" class="flex flex-col">
    <flux:card.header>
        <flux:card.heading size="lg">{{ __('Recently added items') }}</flux:card.heading>
        <flux:card.actions>
            <flux:button size="sm" variant="ghost" icon:trailing="arrow-right" :href="route('inventory.index')" wire:navigate>
                {{ __('Inventory') }}
            </flux:button>
        </flux:card.actions>
    </flux:card.header>

    <flux:card.body class="flex-1">
        @if ($this->items->isEmpty())
            <div class="py-6 text-center">
                <flux:icon name="archive-box" class="mx-auto mb-3 size-10 text-zinc-400" />
                <flux:text>{{ __('Keep track of what\'s in each bin, shelf, and closet.') }}</flux:text>
                <flux:button size="sm" class="mt-4" icon="plus" :href="route('inventory.index')" wire:navigate>
                    {{ __('Start your inventory') }}
                </flux:button>
            </div>
        @else
            <flux:card.bleed class="divide-y divide-zinc-900/5 dark:divide-white/10">
                @foreach ($this->items as $item)
                    <a
                        wire:key="dashboard-item-{{ $item->id }}"
                        href="{{ route('inventory.show', $item) }}"
                        wire:navigate
                        class="flex items-center gap-3 px-(--flux-bleed-x) py-3 hover:bg-zinc-900/2 dark:hover:bg-white/3"
                    >
                        <flux:avatar size="sm" :src="$item->photo_url" :icon="$item->type->getIcon()" :color="$item->type->getIconColor()" icon:variant="outline" />

                        <div class="min-w-0 flex-1">
                            <flux:heading class="truncate">{{ $item->truncated_name }}</flux:heading>
                            @if ($item->parent)
                                <flux:text size="sm" class="truncate">{{ __('in :parent', ['parent' => $item->parent->name]) }}</flux:text>
                            @endif
                        </div>

                        <flux:text size="sm" variant="subtle" class="shrink-0">{{ $item->created_at->diffForHumans(short: true) }}</flux:text>
                    </a>
                @endforeach
            </flux:card.bleed>
        @endif
    </flux:card.body>
</flux:card>
