<div class="space-y-6">
    <div class="flex items-center justify-between">
        <flux:heading size="xl">{{ __('Lists') }}</flux:heading>
        <flux:button variant="primary" wire:click="create">{{ __('Add List') }}</flux:button>
    </div>

    <flux:text>
        {{ __('Shared lists that stay put until someone checks them off — groceries, wish lists, anything to do.') }}
    </flux:text>

    @if ($this->lists->isEmpty())
        <div class="rounded-lg border border-dashed border-zinc-300 p-12 text-center dark:border-zinc-700">
            <flux:icon name="queue-list" class="mx-auto mb-4 size-10 text-zinc-400" />
            <flux:heading size="lg">{{ __('No lists yet') }}</flux:heading>
            <flux:text class="mt-1">
                {{ __('Start a grocery list, or give everyone a wish list of their own.') }}
            </flux:text>
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($this->lists as $list)
                <flux:card size="sm" wire:key="checklist-{{ $list->id }}" class="flex flex-col gap-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-start gap-3">
                            <flux:icon :name="$list->type->getIcon()" class="mt-0.5 size-5 text-zinc-400" />

                            <div>
                                <flux:link :href="route('lists.show', $list)" wire:navigate variant="ghost">
                                    <flux:heading>{{ $list->name }}</flux:heading>
                                </flux:link>

                                <flux:text size="sm">
                                    {{ $list->type->getLabel() }}
                                </flux:text>
                            </div>
                        </div>

                        <flux:dropdown>
                            <flux:button icon="ellipsis-horizontal" variant="ghost" size="sm" />

                            <flux:menu>
                                <flux:menu.item icon="pencil" wire:click="edit({{ $list->id }})">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item icon="list-bullet" :href="route('lists.show', $list)" wire:navigate>{{ __('Items') }}</flux:menu.item>
                                <flux:menu.separator />
                                <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $list->id }})">
                                    {{ __('Delete') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge size="sm" :color="$list->user ? 'blue' : 'zinc'">
                            {{ $list->user?->name ?? __('Household') }}
                        </flux:badge>

                        <flux:badge size="sm" color="zinc">
                            {{ __(':done of :total done', ['done' => $list->completed_items_count, 'total' => $list->items_count]) }}
                        </flux:badge>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @endif

    @include('pages.lists.modals.checklist-form')
</div>
