<div class="group/lists flex h-full flex-col overflow-hidden" wire:poll.600s>
    <div class="flex shrink-0 flex-col gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
        <x-ui.clock :timezone="$this->team->timezone" />

        @if ($this->list?->items->isNotEmpty())
            <div class="hidden group-has-data-completed/lists:block">
                <flux:button variant="subtle" icon="trash" wire:click="clearCompleted">
                    {{ __('Clear completed') }}
                </flux:button>
            </div>
        @endif
    </div>

    @if ($this->lists->isEmpty())
        <div class="flex flex-1 flex-col items-center justify-center gap-3 p-6 text-center">
            <flux:icon name="queue-list" class="size-12 text-zinc-400" />
            <flux:heading size="lg">{{ __('No lists yet') }}</flux:heading>
            <flux:text>
                {{ __('Open Sunny on your phone or computer to add one.') }}
            </flux:text>
        </div>
    @else
        <div class="flex shrink-0 gap-2 overflow-x-auto border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
            @foreach ($this->lists as $list)
                <button
                    type="button"
                    wire:key="checklist-tab-{{ $list->id }}"
                    wire:click="select({{ $list->id }})"
                    @class([
                        'flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 transition',
                        'border-transparent bg-(--color-accent) text-(--color-accent-foreground)' => $this->list?->is($list),
                        'border-zinc-200 text-zinc-700 hover:border-zinc-300 dark:border-zinc-700 dark:text-zinc-300' => ! $this->list?->is($list),
                    ])
                >
                    <flux:icon :name="$list->type->getIcon()" class="size-5" />
                    <span>{{ $list->name }}</span>
                </button>
            @endforeach
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-5">
            <div class="mx-auto flex w-full max-w-2xl flex-col gap-4">
                <div class="flex items-baseline justify-between gap-3">
                    <flux:heading size="xl">{{ $this->list->name }}</flux:heading>

                    @if ($this->list->user)
                        <flux:text>{{ $this->list->user->name }}</flux:text>
                    @endif
                </div>

                <form wire:submit="addItem" class="flex gap-2">
                    <flux:input
                        wire:model="newItem"
                        :placeholder="__('Add an item')"
                        class:input="text-lg"
                    />
                    <flux:button type="submit" variant="primary" icon="plus">{{ __('Add') }}</flux:button>
                </form>

                @if ($this->list->items->isEmpty())
                    <flux:text class="py-8 text-center">
                        {{ __('This list is empty.') }}
                    </flux:text>
                @else
                    <ul class="flex flex-col gap-2">
                        @foreach ($this->list->items as $item)
                            <li
                                wire:key="checklist-item-{{ $item->id }}-{{ $item->completed_at?->getTimestamp() ?? 'open' }}"
                                x-data="{
                                    completed: @js($item->isCompleted()),
                                    completedBy: @js($item->isCompleted() ? $item->completedBy?->name : null),
                                    async toggle() {
                                        let previous = { completed: this.completed, completedBy: this.completedBy }

                                        this.completed = ! this.completed
                                        this.completedBy = this.completed ? @js(auth()->user()->name) : null

                                        try {
                                            await $wire.setItemCompleted({{ $item->id }}, this.completed)
                                        } catch {
                                            this.completed = previous.completed
                                            this.completedBy = previous.completedBy
                                        }
                                    },
                                }"
                                class="flex items-center gap-2"
                            >
                                <button
                                    type="button"
                                    x-on:click="toggle"
                                    x-bind:data-completed="completed"
                                    @if ($item->isCompleted()) data-completed @endif
                                    class="group flex flex-1 items-center gap-3 rounded-lg border p-4 text-left transition not-data-completed:border-zinc-200 not-data-completed:bg-white not-data-completed:hover:border-zinc-300 data-completed:border-transparent data-completed:bg-zinc-100 dark:not-data-completed:border-zinc-700 dark:not-data-completed:bg-zinc-900 dark:not-data-completed:hover:border-zinc-600 dark:data-completed:bg-zinc-800/50"
                                >
                                    <flux:icon name="check-circle" variant="solid" class="hidden size-6 shrink-0 text-(--color-accent) group-data-completed:block" />
                                    <span class="size-6 shrink-0 rounded-full border-2 border-zinc-300 group-data-completed:hidden dark:border-zinc-600"></span>

                                    <span class="flex-1 text-lg text-zinc-800 group-data-completed:text-zinc-400 group-data-completed:line-through dark:text-zinc-100 dark:group-data-completed:text-zinc-500">
                                        {{ $item->name }}
                                    </span>

                                    <flux:text
                                        size="sm"
                                        variant="subtle"
                                        class="shrink-0"
                                        x-show="completedBy"
                                        x-text="completedBy"
                                    >{{ $item->isCompleted() ? $item->completedBy?->name : '' }}</flux:text>
                                </button>

                                <flux:button
                                    variant="subtle"
                                    icon="x-mark"
                                    wire:click="removeItem({{ $item->id }})"
                                    :aria-label="__('Remove :item', ['item' => $item->name])"
                                />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
</div>
