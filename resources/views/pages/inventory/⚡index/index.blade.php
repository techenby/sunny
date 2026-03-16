<div class="space-y-6">
    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:click="$set('parentId', null)" class="cursor-pointer">
            {{ __('Inventory') }}
        </flux:breadcrumbs.item>

        @foreach ($this->breadcrumbs as $breadcrumb)
            <flux:breadcrumbs.item wire:click="navigateDown({{ $breadcrumb->id }})" class="cursor-pointer">
                {{ $breadcrumb->name }}
            </flux:breadcrumbs.item>
        @endforeach
    </flux:breadcrumbs>

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            @if ($parentId === null)
            <flux:heading size="xl">{{ __('All Items') }}</flux:heading>
            @else
            <flux:button wire:click="navigateUp" icon="arrow-left" variant="ghost" />
            <flux:heading size="xl">{{ $breadcrumb->name }}</flux:heading>
            @endif
        </div>
        <div class="flex items-center gap-1">
            <flux:button variant="primary" wire:click="create">{{ __('Add Item') }}</flux:button>
            <flux:dropdown>
                <flux:button icon="chevron-down" variant="ghost" />

                <flux:menu>
                    <div>
                        <flux:modal.trigger name="import-items">
                            <flux:menu.item icon="document-arrow-up">{{ __('Import') }}</flux:menu.item>
                        </flux:modal.trigger>

                        @include('pages.inventory.modals.import-items')
                    </div>
                </flux:menu>
            </flux:dropdown>
        </div>
    </div>

    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-1">
            <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search inventory...')" icon="magnifying-glass" class="max-w-sm" />
            @if ($selected !== [])
            <flux:dropdown>
                <flux:button icon:trailing="chevron-down">{{ __('Bulk Actions') }}</flux:button>

                <flux:menu>
                    <flux:menu.item wire:click="openBulkUpdateParentModal">{{ __('Change Parent') }}</flux:menu.item>

                    <flux:menu.separator />

                    <flux:menu.item wire:click="delete" wire:confirm="{{ __('Are you sure you want to delete the selected items?') }}" variant="danger" icon="trash">{{ __('Delete') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
            @endif
        </div>
        <flux:switch wire:model.live="showTrashed" label="{{ __('Show deleted') }}" />
    </div>

    <flux:checkbox.group wire:model.live="selected">
    <flux:table :paginate="$this->items">
        <flux:table.columns>
            <flux:table.column class="w-8"><flux:checkbox.all /></flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Modified') }}</flux:table.column>
            <flux:table.column>{{ __('Contents') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->items as $item)
                <flux:table.row :key="$item->id">
                    <flux:table.cell>
                        <flux:checkbox :value="$item->id"/>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:link wire:click="navigateDown({{ $item->id }})" as="button" inset="top bottom" class="!flex !items-center gap-3">
                            <flux:avatar size="xs" :icon="$item->type->getIcon()" :color="$item->type->getIconColor()" icon:variant="outline" />
                            <span>{{ $item->truncated_name }}</span>
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $item->updated_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $item->children_count }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($showTrashed)
                            <div class="flex items-center gap-1">
                                <flux:button wire:click="restore({{ $item->id }})" wire:confirm="{{ __('Are you sure you want to restore this item?') }}" variant="ghost" size="sm" icon="arrow-uturn-left">{{ __('Restore') }}</flux:button>
                                <flux:button wire:click="forceDelete({{ $item->id }})" wire:confirm="{{ __('Are you sure you want to permanently delete this item? This cannot be undone.') }}" variant="danger" size="sm" icon="trash">{{ __('Delete Forever') }}</flux:button>
                            </div>
                        @else
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />

                                <flux:menu>
                                    <flux:menu.item wire:click="edit({{ $item->id }})" icon="pencil">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item wire:click="showQrCode({{ $item->id }})" icon="qr-code">{{ __('QR Code') }}</flux:menu.item>
                                    @if ($this->otherTeams->isNotEmpty())
                                        <flux:menu.item wire:click="openMoveModal({{ $item->id }})" icon="arrow-up-tray">{{ __('Move to Team') }}</flux:menu.item>
                                    @endif
                                    <flux:menu.item wire:click="delete({{ $item->id }})" variant="danger" icon="trash" wire:confirm="{{ __('Are you sure you want to delete this item?') }}">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row key="empty-item">
                    <flux:table.cell colspan="5" class="text-center">
                        <flux:text variant="subtle" size="xl">{{ __('No items found') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
    </flux:checkbox.group>

    @include('pages.inventory.modals.item-form')
    @include('pages.inventory.modals.qr-code')
    @include('pages.inventory.modals.move-item')
    @include('pages.inventory.modals.bulk-update-parent')
</div>
