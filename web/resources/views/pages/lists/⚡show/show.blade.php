<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <flux:button :href="route('lists.index')" icon="arrow-left" variant="ghost" wire:navigate />

            <div>
                <flux:heading size="xl">{{ $checklist->name }}</flux:heading>
                <flux:text>
                    {{ $checklist->type->getLabel() }} &middot; {{ $checklist->user?->name ?? __('Household') }}
                </flux:text>
            </div>
        </div>

        @if ($this->items->contains(fn ($item) => $item->isCompleted()))
            <flux:dropdown>
                <flux:button icon="ellipsis-horizontal" variant="ghost" />

                <flux:menu>
                    <flux:menu.item icon="arrow-path" wire:click="resetList">{{ __('Uncheck all') }}</flux:menu.item>
                    <flux:menu.item icon="trash" variant="danger" wire:click="clearCompleted">{{ __('Clear completed') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        @endif
    </div>

    <div class="max-w-2xl space-y-4">
        @if ($this->items->isEmpty())
            <flux:text>{{ __('This list is empty.') }}</flux:text>
        @else
            <ul class="space-y-2">
                @foreach ($this->items as $item)
                    <li wire:key="item-{{ $item->id }}" class="flex items-center gap-2">
                        <button
                            type="button"
                            wire:click="toggle({{ $item->id }})"
                            class="flex flex-1 items-center gap-3 rounded-lg border border-zinc-200 p-3 text-left transition hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600"
                        >
                            @if ($item->isCompleted())
                                <flux:icon name="check-circle" variant="solid" class="size-5 shrink-0 text-(--color-accent)" />
                            @else
                                <span class="size-5 shrink-0 rounded-full border-2 border-zinc-300 dark:border-zinc-600"></span>
                            @endif

                            <span @class([
                                'flex-1',
                                'text-zinc-400 line-through dark:text-zinc-500' => $item->isCompleted(),
                            ])>
                                {{ $item->name }}
                            </span>

                            @if ($item->isCompleted() && $item->completedBy)
                                <flux:text size="sm" variant="subtle" class="shrink-0">
                                    {{ $item->completedBy->name }}
                                </flux:text>
                            @endif
                        </button>

                        <flux:button
                            icon="trash"
                            variant="ghost"
                            wire:click="removeItem({{ $item->id }})"
                            :aria-label="__('Remove :item', ['item' => $item->name])"
                        />
                    </li>
                @endforeach
            </ul>
        @endif

        <form wire:submit="addItem" class="flex gap-2">
            <flux:input wire:model="newItem" :placeholder="__('Add an item')" />
            <flux:button type="submit" variant="primary" icon="plus">{{ __('Add') }}</flux:button>
        </form>
    </div>
</div>
