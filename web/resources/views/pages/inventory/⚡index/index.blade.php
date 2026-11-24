<div class="space-y-6">
    @if (filled($search) || $parentId !== null)
    <flux:breadcrumbs>
        <flux:breadcrumbs.item wire:click="$set('parentId', null)" class="cursor-pointer">
            {{ __('Inventory') }}
        </flux:breadcrumbs.item>

        @if (filled($search))
            <flux:breadcrumbs.item>{{ __('Search') }}</flux:breadcrumbs.item>
        @else
            @foreach ($this->breadcrumbs as $breadcrumb)
                <flux:breadcrumbs.item wire:click="navigateDown({{ $breadcrumb->id }})" class="cursor-pointer">
                    {{ $breadcrumb->name }}
                </flux:breadcrumbs.item>
            @endforeach
        @endif
    </flux:breadcrumbs>
    @endif

    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            @if (filled($search))
            <div>
                <flux:heading size="xl">{{ __('Results for “:search”', ['search' => $search]) }}</flux:heading>
                <flux:text>{{ __('Looking through all of your inventory.') }}</flux:text>
            </div>
            <flux:button wire:click="$set('search', '')" icon="x-mark" size="sm">{{ __('Clear search') }}</flux:button>
            @elseif ($parentId === null)
            <div>
                <flux:heading size="xl">{{ __('Inventory') }}</flux:heading>
                <flux:text>{{ __('Every room, bin and box in your home, and what’s inside.') }}</flux:text>
            </div>
            @else
            <flux:heading size="xl">{{ $breadcrumb->name }}</flux:heading>
            @endif
        </div>
        <div class="flex items-center gap-1">
            <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add') }}</flux:button>
        </div>
    </div>

    @if ($this->emptyPlace)
    @include('pages.inventory.empty-place', ['place' => $this->emptyPlace])
    @else
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
    @if ($this->isGroupedByPlace() && $this->items->isNotEmpty())
        <div class="space-y-6">
            @foreach ($this->items->getCollection()->groupBy(fn ($item) => $item->type === App\Enums\ItemType::Location ? 'places' : 'unplaced') as $group => $groupItems)
                <flux:card wire:key="group-{{ $group }}" class="overflow-hidden p-0!">
                    <div class="px-6 pt-5 pb-4">
                        <div class="flex items-baseline gap-2">
                            @if ($group === 'places')
                                <flux:heading size="lg">{{ __('Places in your home') }}</flux:heading>
                                <flux:text size="sm" variant="subtle">{{ trans_choice(':count place|:count places', $this->groupCounts['places']) }}</flux:text>
                            @else
                                <flux:heading size="lg">{{ __('Not in a place yet') }}</flux:heading>
                                <flux:text size="sm" variant="subtle">{{ trans_choice(':count thing|:count things', $this->groupCounts['unplaced']) }}</flux:text>
                            @endif
                        </div>
                        <flux:text class="mt-1">
                            {{ $group === 'places' ? __('Rooms, cars and storage your things live in.') : __('Open one and choose Move to put it away.') }}
                        </flux:text>
                    </div>

                    <flux:table class="w-full border-t border-zinc-800/10 dark:border-white/20 [&_td:first-child]:ps-6 [&_td:last-child]:pe-6 [&_th:first-child]:ps-6 [&_th:last-child]:pe-6">
                        <flux:table.columns>
                            <flux:table.column class="w-14">
                                <label class="relative block size-[1.125rem]">
                                    <input
                                        type="checkbox"
                                        class="peer absolute inset-0 size-full appearance-none rounded-[.3rem] border border-zinc-300 bg-white shadow-xs checked:border-transparent checked:bg-(--color-accent) dark:border-white/10 dark:bg-white/10"
                                        x-data="{ ids: @js($groupItems->pluck('id')->map(fn ($id) => (string) $id)->values()->all()) }"
                                        x-bind:checked="ids.every((id) => $wire.selected.map(String).includes(id))"
                                        x-on:change="$el.closest('table').querySelectorAll('tbody ui-checkbox').forEach((box) => box.hasAttribute('data-checked') !== $event.target.checked && box.click())"
                                        aria-label="{{ $group === 'places' ? __('Select every place on this page') : __('Select everything not in a place on this page') }}"
                                    />
                                    <flux:icon.check variant="micro" class="pointer-events-none absolute inset-0 m-auto hidden size-3.5 text-(--color-accent-foreground) peer-checked:block" />
                                </label>
                            </flux:table.column>
                            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
                            <flux:table.column class="w-45">{{ __('Modified') }}</flux:table.column>
                            <flux:table.column class="w-45">{{ __('What’s inside') }}</flux:table.column>
                            <flux:table.column class="w-20" align="end"></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($groupItems as $item)
                                @include('pages.inventory.row')
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </flux:card>
            @endforeach

            <flux:pagination :paginator="$this->items" class="border-t-0! pt-0!" />
        </div>
    @else
    <flux:table :paginate="$this->items" class="w-full">
        <flux:table.columns>
            <flux:table.column class="w-8"><flux:checkbox.all /></flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            @if (filled($search))
                <flux:table.column class="w-90">{{ __('Where it is') }}</flux:table.column>
            @else
                <flux:table.column class="w-45">{{ __('Modified') }}</flux:table.column>
                <flux:table.column class="w-45">{{ __('What’s inside') }}</flux:table.column>
            @endif
            <flux:table.column class="w-14" align="end"></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->items as $item)
                @include('pages.inventory.row')
            @empty
                <flux:table.row key="empty-item">
                    <flux:table.cell :colspan="filled($search) ? 4 : 5" class="py-8 text-center">
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
    @endif
    </flux:checkbox.group>
    @endif

    @include('pages.inventory.modals.item-form')
    @include('pages.inventory.modals.qr-code')
    @include('pages.inventory.modals.move-item')
    @include('pages.inventory.modals.duplicate-item')
    @include('pages.inventory.modals.bulk-update-parent')
</div>
