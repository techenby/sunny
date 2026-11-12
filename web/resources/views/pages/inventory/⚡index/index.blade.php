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
            <flux:heading size="xl">{{ __('All of your inventory') }}</flux:heading>
            @else
            <flux:heading size="xl">{{ $breadcrumb->name }}</flux:heading>
            @endif
        </div>
        <div class="flex items-center gap-1">
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add') }}</flux:button>
            <flux:dropdown>
                <flux:button icon="chevron-down" variant="ghost" :aria-label="__('More ways to add')" />

                <flux:menu>
                    <div>
                        <flux:modal.trigger name="import-items">
                            <flux:menu.item icon="document-arrow-up">{{ __('Import from Amazon') }}</flux:menu.item>
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
            <flux:dropdown>
                <flux:button :variant="$this->areFiltersActive ? 'primary' : 'ghost'" :color="$this->areFiltersActive ? 'sky' : ''" icon="funnel" icon:variant="outline" :aria-label="__('Filter')" />

                <flux:menu>
                    <flux:menu.checkbox.group wire:model.live="filters.types">
                        @foreach (App\Enums\ItemType::cases() as $type)
                            <flux:menu.checkbox :value="$type->value">{{ $type->label() }}</flux:menu.checkbox>
                        @endforeach
                    </flux:menu.checkbox.group>
                    <flux:menu.separator />
                    <flux:menu.checkbox wire:model.live="filters.showTrashed">{{ __('Show deleted') }}</flux:menu.checkbox>
                </flux:menu>
            </flux:dropdown>
            @if ($selected !== [])
            <flux:dropdown>
                <flux:button icon:trailing="chevron-down">{{ __(':count selected', ['count' => count($selected)]) }}</flux:button>

                <flux:menu>
                    <flux:menu.item wire:click="openBulkUpdateParentModal" icon="arrow-right">{{ __('Move') }}</flux:menu.item>

                    <flux:menu.separator />

                    @if ($filters['showTrashed'] ?? false)
                    <flux:menu.item wire:click="bulkRestore" icon="arrow-uturn-left">{{ __('Restore') }}</flux:menu.item>
                    @else
                    <flux:menu.item wire:click="bulkDelete" wire:confirm="{{ trans_choice('The :count thing you selected will be deleted for everyone on your team. Anything inside it will move to “Not in a place yet”.|The :count things you selected will be deleted for everyone on your team. Anything inside them will move to “Not in a place yet”.', count($selected)) }}" variant="danger" icon="trash">{{ __('Delete') }}</flux:menu.item>
                    @endif
                </flux:menu>
            </flux:dropdown>
            @endif
        </div>
    </div>

    <flux:checkbox.group wire:model.live="selected">
    <flux:table :paginate="$this->items">
        <flux:table.columns>
            <flux:table.column class="w-8"><flux:checkbox.all /></flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Modified') }}</flux:table.column>
            <flux:table.column>{{ __('What’s inside') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->items as $item)
                @php($isPlace = $item->type === App\Enums\ItemType::Location)
                @if ($this->isGroupedByPlace() && ($loop->first || $isPlace !== $wasPlace))
                    <flux:table.row :key="$isPlace ? 'group-places' : 'group-unplaced'">
                        <flux:table.cell colspan="5" class="bg-zinc-50 dark:bg-white/5">
                            @if ($isPlace)
                                <flux:heading>{{ __('Places in your home') }}</flux:heading>
                            @else
                                <flux:heading>{{ __('Not in a place yet') }}</flux:heading>
                                <flux:text size="sm">{{ __('Open one and choose Move to put it away.') }}</flux:text>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endif
                @php($wasPlace = $isPlace)
                <flux:table.row :key="$item->id">
                    <flux:table.cell>
                        <flux:checkbox :value="$item->id"/>
                    </flux:table.cell>
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:link wire:click="navigateDown({{ $item->id }})" as="button" inset="top bottom" class="!flex !items-center gap-3">
                                <flux:avatar size="xs" :src="$item->thumb_url" :icon="$item->type->getIcon()" :color="$item->type->getIconColor()" icon:variant="outline" />
                                <span>{{ $item->truncated_name }}</span>
                            </flux:link>
                            @if (filled($search) && $item->parent)
                                <flux:text size="sm">{{ __('in :parent', ['parent' => $item->parent->name]) }}</flux:text>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $item->updated_at->diffForHumans() }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $item->children_count > 0 ? trans_choice(':count thing|:count things', $item->children_count) : '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($filters['showTrashed'] ?? false)
                            <div class="flex items-center gap-1">
                                <flux:button wire:click="restore({{ $item->id }})" wire:confirm="{{ __('Bring “:name” back into your inventory?', ['name' => $item->name]) }}" variant="ghost" size="sm" icon="arrow-uturn-left">{{ __('Restore') }}</flux:button>
                                <flux:button wire:click="forceDelete({{ $item->id }})" wire:confirm="{{ __('“:name” will be deleted forever for everyone on your team. You won’t be able to bring it back.', ['name' => $item->name]) }}" variant="danger" size="sm" icon="trash">{{ __('Delete forever') }}</flux:button>
                            </div>
                        @else
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" :aria-label="__('Actions for :name', ['name' => $item->name])" />

                                <flux:menu>
                                    <flux:menu.item :href="route('inventory.show', ['item' => $item])" icon="eye" wire:navigate>{{ __('View') }}</flux:menu.item>
                                    <flux:menu.item wire:click="edit({{ $item->id }})" icon="pencil">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item wire:click="showQrCode({{ $item->id }})" icon="qr-code">{{ __('QR code') }}</flux:menu.item>
                                    <flux:menu.item wire:click="openDuplicateModal({{ $item->id }})" icon="square-2-stack">{{ __('Make copies') }}</flux:menu.item>
                                    @if ($this->otherTeams->isNotEmpty())
                                        <flux:menu.item wire:click="openMoveModal({{ $item->id }})" icon="arrow-up-tray">{{ __('Move to another team') }}</flux:menu.item>
                                    @endif
                                    <flux:menu.item wire:click="delete({{ $item->id }})" variant="danger" icon="trash" wire:confirm="{{ $item->deletionWarning() }}">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row key="empty-item">
                    <flux:table.cell colspan="5" class="py-8 text-center">
                        @if (filled($search))
                            <flux:heading size="lg">{{ __('Nothing called “:search”', ['search' => $search]) }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Check the spelling, or try a shorter word.') }}</flux:text>
                        @elseif ($this->areFiltersActive || filled($filters['types'] ?? []))
                            <flux:heading size="lg">{{ __('Nothing matches these filters') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Turn a filter off to see more.') }}</flux:text>
                        @elseif ($parentId === null)
                            <flux:heading size="lg">{{ __('Nothing here yet') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Choose Add to start with a room, like Garage.') }}</flux:text>
                        @else
                            <flux:heading size="lg">{{ __('Nothing inside yet') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Choose Add to put something here.') }}</flux:text>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
    </flux:checkbox.group>

    @include('pages.inventory.modals.item-form')
    @include('pages.inventory.modals.qr-code')
    @include('pages.inventory.modals.move-item')
    @include('pages.inventory.modals.duplicate-item')
    @include('pages.inventory.modals.bulk-update-parent')
</div>
