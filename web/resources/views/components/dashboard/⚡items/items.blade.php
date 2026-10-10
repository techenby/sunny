<x-dashboard.card :heading="__('Recently added items')" :action="__('Inventory')" :href="route('inventory.index')">
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
                <x-dashboard.row
                    wire:key="dashboard-item-{{ $item->id }}"
                    :href="route('inventory.show', $item)"
                    :heading="$item->truncated_name"
                    :subheading="$item->parent ? __('in :parent', ['parent' => $item->parent->name]) : null"
                    :meta="$item->created_at->diffForHumans(short: true)"
                >
                    <x-slot:avatar>
                        <flux:avatar size="sm" :src="$item->thumb_url" :icon="$item->type->getIcon()" :color="$item->type->getIconColor()" icon:variant="outline" />
                    </x-slot:avatar>
                </x-dashboard.row>
            @endforeach
        </flux:card.bleed>
    @endif
</x-dashboard.card>
