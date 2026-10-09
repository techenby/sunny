<x-dashboard.card :heading="__('Open lists')" :action="__('All lists')" :href="route('lists.index')">
    @if ($this->lists->isEmpty())
        <div class="py-6 text-center">
            <flux:icon name="queue-list" class="mx-auto mb-3 size-10 text-zinc-400" />
            <flux:text>{{ __('All caught up. Nothing left on your lists.') }}</flux:text>
        </div>
    @else
        <div class="space-y-5">
            @foreach ($this->lists as $list)
                <x-dashboard.list
                    wire:key="dashboard-list-{{ $list->id }}"
                    :name="$list->name"
                    :icon="$list->type->getIcon()"
                    :color="$list->type->getIconColor()"
                    :href="route('lists.show', $list)"
                    :household="$list->user === null"
                >
                    <ul>
                        @foreach ($list->items->take($this::ITEMS_PER_LIST) as $item)
                            <li wire:key="dashboard-list-item-{{ $item->id }}">
                                <x-dashboard.check-item wire:click="toggle({{ $item->id }})">
                                    {{ $item->name }}
                                </x-dashboard.check-item>
                            </li>
                        @endforeach
                    </ul>

                    @if ($list->items->count() > $this::ITEMS_PER_LIST)
                        <flux:link :href="route('lists.show', $list)" wire:navigate class="text-sm">
                            {{ __(':count more', ['count' => $list->items->count() - $this::ITEMS_PER_LIST]) }}
                        </flux:link>
                    @endif
                </x-dashboard.list>
            @endforeach
        </div>
    @endif
</x-dashboard.card>
