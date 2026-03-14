<section class="w-full">
    <div class="mb-4 flex items-center justify-between">
        <flux:heading size="xl">{{ __('Inventory') }}</flux:heading>
        <div class="flex items-center gap-1">
            <flux:button variant="primary" wire:click="create">{{ __('Add Item') }}</flux:button>
            <flux:dropdown>
                <flux:button icon="chevron-down" variant="ghost" />

                <flux:menu>
                    <div>
                        <flux:modal.trigger name="import-items">
                            <flux:menu.item icon="document-arrow-up">{{ __('Import') }}</flux:menu.item>
                        </flux:modal.trigger>

                        @teleport('body')
                        @include('pages.inventory.modals.import-items')
                        @endteleport
                    </div>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search inventory...')" icon="magnifying-glass" class="max-w-sm" />
    </div>

    <div class="mb-4 flex items-center gap-2">
        @if ($this->parentId)
            <flux:button variant="ghost" size="sm" icon="arrow-left" wire:click="navigateUp" />
        @endif

        <flux:breadcrumbs>
            <flux:breadcrumbs.item wire:click="$set('parentId', null)" class="cursor-pointer">
                {{ __('All') }}
            </flux:breadcrumbs.item>

            @foreach ($this->breadcrumbs as $breadcrumb)
                <flux:breadcrumbs.item wire:click="navigateDown({{ $breadcrumb->id }})" class="cursor-pointer">
                    {{ $breadcrumb->name }}
                </flux:breadcrumbs.item>
            @endforeach
        </flux:breadcrumbs>
    </div>

    <flux:table :paginate="$this->items">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Modified') }}</flux:table.column>
            <flux:table.column>{{ __('Contents') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->items as $item)
                <flux:table.row :key="$item->id">
                    <flux:table.cell>
                        <flux:link wire:click="navigateDown({{ $item->id }})" inset="top bottom" class="!flex !items-center gap-3">
                            <flux:avatar size="xs" :icon="$item->type->getIcon()" :color="$item->type->getIconColor()" icon:variant="outline" />
                            <span>{{ $item->truncated_name }}</span>
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $item->updated_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $item->children_count }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />

                            <flux:menu>
                                <flux:menu.item wire:click="edit({{ $item->id }})" icon="pencil">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item wire:click="delete({{ $item->id }})" variant="danger" icon="trash" wire:confirm="{{ __('Are you sure you want to delete this item?') }}">{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row key="empty-item">
                    <flux:table.cell colspan="4" class="text-center">
                        <flux:text variant="subtle" size="xl">{{ __('No items found') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @teleport('body')
    @include('pages.inventory.modals.item-form')
    @endteleport
</section>
