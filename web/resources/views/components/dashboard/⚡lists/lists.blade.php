<x-dashboard.card :heading="__('Open lists')" :action="__('All lists')" :href="route('lists.index')">
    @if ($this->lists->isEmpty())
        <x-dashboard.empty
            icon="queue-list"
            :heading="__('No open lists')"
            :text="__('All caught up. Nothing left on your lists.')"
            :action="__('Add a list')"
            :href="route('lists.index')"
        />
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
                        @foreach ($list->items as $item)
                            <li wire:key="dashboard-list-item-{{ $item->id }}">
                                <x-dashboard.check-item wire:click="setItemCompleted({{ $item->id }}, {{ $item->isCompleted() ? 'false' : 'true' }})">
                                    {{ $item->name }}
                                </x-dashboard.check-item>
                            </li>
                        @endforeach
                    </ul>

                    @if ($list->open_items_count > $list->items->count())
                        <flux:link :href="route('lists.show', $list)" wire:navigate class="text-sm">
                            {{ __(':count more', ['count' => $list->open_items_count - $list->items->count()]) }}
                        </flux:link>
                    @endif
                </x-dashboard.list>
            @endforeach

            @if ($this->total > $this->lists->count())
                <x-dashboard.more
                    :href="route('lists.index')"
                    :label="trans_choice(':count more list|:count more lists', $this->total - $this->lists->count(), ['count' => $this->total - $this->lists->count()])"
                    :note="__('Showing :shown of :total open lists', ['shown' => $this->lists->count(), 'total' => $this->total])"
                />
            @endif
        </div>
    @endif
</x-dashboard.card>
