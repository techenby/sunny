<x-dashboard.card :heading="__('Recently added items')" :action="__('Inventory')" :href="route('inventory.index')">
    @if ($this->items->isEmpty())
        <x-dashboard.empty
            icon="archive-box"
            tile="bg-orange-100 text-orange-700 dark:bg-orange-400/40 dark:text-orange-200"
            :heading="__('No items yet')"
            :text="__('Keep track of what\'s in each bin, shelf, and closet.')"
            :action="__('Start your inventory')"
            :href="route('inventory.index')"
        />
    @else
        <flux:card.bleed class="divide-y divide-zinc-900/5 dark:divide-white/10">
            @foreach ($this->items as $item)
                <x-dashboard.row
                    wire:key="dashboard-item-{{ $item->id }}"
                    :href="route('inventory.show', $item)"
                    :heading="$item->truncated_name"
                    :subheading="$item->parent ? __('in :parent', ['parent' => $item->parent->name]) : null"
                    :meta="$item->created_at->diffForHumans()"
                    :src="$item->thumb_url"
                    :icon="$item->type->getIcon()"
                    tile="bg-orange-100 text-orange-700 dark:bg-orange-400/40 dark:text-orange-200"
                />
            @endforeach
        </flux:card.bleed>
    @endif
</x-dashboard.card>
