<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('lists.index')" wire:navigate class="cursor-pointer">{{ __('Lists') }}</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>{{ $checklist->name }}</flux:breadcrumbs.item>
    </flux:breadcrumbs>

    <div class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div>
                <flux:heading size="xl">{{ $checklist->name }}</flux:heading>
                <flux:text>
                    {{ $checklist->type->getLabel() }} &middot; {{ $checklist->user?->name ?? __('Household') }}
                </flux:text>
            </div>
        </div>

        @if ($this->items->contains(fn ($item) => $item->isCompleted()))
            <x-ui.actions size="base">
                <flux:menu.item icon="arrow-path" wire:click="resetList">{{ __('Uncheck all') }}</flux:menu.item>
                <flux:menu.item icon="trash" variant="danger" wire:click="clearCompleted" wire:confirm="{{ $this->deleteDoneMessage() }}">{{ __('Delete done items') }}</flux:menu.item>
            </x-ui.actions>
        @endif
    </div>

    <div class="max-w-2xl space-y-4">
        @if ($this->items->isEmpty())
            <flux:text>{{ __('This list is empty.') }}</flux:text>
        @else
            <ul class="space-y-2">
                @foreach ($this->items as $item)
                    <li wire:key="item-{{ $item->id }}" class="group flex items-center gap-2">
                        @if ($editingItemId === $item->id)
                            <form wire:submit="updateItem" wire:keydown.escape="cancelEdit" class="flex flex-1 items-start gap-2">
                                <div class="flex-1 space-y-2">
                                    <flux:input
                                        wire:model="editingName"
                                        :aria-label="__('Item name')"
                                        autofocus
                                    />
                                    <flux:error name="editingName" />
                                </div>
                                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
                                <flux:button variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                            </form>
                        @else
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

                            @if ($item->url)
                                <flux:button
                                    icon="arrow-top-right-on-square"
                                    icon:variant="outline"
                                    variant="ghost"
                                    class="text-zinc-500! disabled:text-zinc-400! disabled:opacity-100! dark:text-zinc-400! dark:disabled:text-zinc-600!"
                                    :href="$item->url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    :aria-label="__('Open link for :item', ['item' => $item->name])"
                                />
                            @endif

                            <div class="flex shrink-0 items-center gap-2 transition-opacity pointer-fine:opacity-0 pointer-fine:group-hover:opacity-100 pointer-fine:group-focus-within:opacity-100">
                                <flux:button
                                    icon="pencil-square"
                                    icon:variant="outline"
                                    variant="ghost"
                                    class="text-zinc-500! disabled:text-zinc-400! disabled:opacity-100! dark:text-zinc-400! dark:disabled:text-zinc-600!"
                                    wire:click="editItem({{ $item->id }})"
                                    :aria-label="__('Edit :item', ['item' => $item->name])"
                                />

                                <flux:button
                                    icon="trash"
                                    icon:variant="outline"
                                    variant="ghost"
                                    class="text-zinc-500! hover:text-red-600! dark:text-zinc-400! dark:hover:text-red-400!"
                                    wire:click="removeItem({{ $item->id }})"
                                    :aria-label="__('Remove :item', ['item' => $item->name])"
                                />
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <form wire:submit="addItem" class="flex flex-col gap-2 sm:flex-row">
            <flux:input wire:model="newItem" :placeholder="__('Add an item')" />
            @if ($checklist->type === \App\Enums\ChecklistType::Wishlist)
                <flux:input wire:model="newItemUrl" type="url" icon="link" :placeholder="__('Link (optional)')" />
            @endif
            <flux:button type="submit" variant="primary" icon="plus">{{ __('Add') }}</flux:button>
        </form>
    </div>
</div>
