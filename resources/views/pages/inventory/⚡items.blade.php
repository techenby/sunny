<?php

use App\Enums\ItemType;
use App\Livewire\Forms\Inventory\ItemForm;
use App\Livewire\Traits\WithSorting;
use App\Livewire\Traits\WithSearching;
use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;
    use WithSearching;
    use WithSorting;

    public ItemForm $form;

    #[Url(history: true)]
    public ?int $parentId = null;

    public function drillDown(int $id): void
    {
        $this->parentId = $id;
        $this->resetPage();
    }

    public function navigateUp(): void
    {
        if ($this->parentId) {
            $parent = Auth::user()->currentTeam->items()
                ->where('id', $this->parentId)
                ->first();

            $this->parentId = $parent?->parent_id;
            $this->resetPage();
        }
    }

    #[Computed]
    public function breadcrumbs(): Collection
    {
        $breadcrumbs = collect();
        $current = $this->parentId
            ? Auth::user()->currentTeam->items()
                ->where('id', $this->parentId)
                ->first()
            : null;

        while ($current) {
            $breadcrumbs->prepend($current);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return Auth::user()->currentTeam->items()
            ->when($this->parentId, fn ($query) => $query->where('parent_id', $this->parentId))
            ->when(! $this->parentId, fn ($query) => $query->whereNull('parent_id'))
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->withCount('children')
            ->withAllItemsCount()
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }

    #[Computed]
    public function parentContainers(): Collection
    {
        return Auth::user()->currentTeam->items()
            ->whereIn('type', [ItemType::Location, ItemType::Bin])
            ->when($this->form->editingItem, fn ($query) => $query->where('id', '!=', $this->form->editingItem->id))
            ->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        $this->form->fill(['parent_id' => $this->parentId]);
        $this->modal('item-form')->show();
    }

    public function edit(int $id): void
    {
        $this->form->load(
            Auth::user()->currentTeam->items()->findOrFail($id)
        );

        $this->modal('item-form')->show();
    }

    public function save(): void
    {
        $this->form->save();

        $this->modal('item-form')->close();

        unset($this->items, $this->parentContainers);
    }

    public function delete(int $id): void
    {
        Auth::user()->currentTeam->items()
            ->where('id', $id)
            ->firstOrFail()
            ->delete();

        unset($this->items);
    }
}; ?>

<section class="w-full">
    @include('pages.inventory.heading')

    <div class="mb-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-2">
            @if ($this->parentId)
                <flux:button variant="ghost" size="sm" icon="arrow-left" wire:click="navigateUp" />
            @endif

            <flux:breadcrumbs>
                <flux:breadcrumbs.item wire:click="$set('parentId', null)" class="cursor-pointer">
                    {{ __('All') }}
                </flux:breadcrumbs.item>

                @foreach ($this->breadcrumbs as $breadcrumb)
                    <flux:breadcrumbs.item wire:click="drillDown({{ $breadcrumb->id }})" class="cursor-pointer">
                        {{ $breadcrumb->name }}
                    </flux:breadcrumbs.item>
                @endforeach
            </flux:breadcrumbs>
        </div>

        <div class="flex gap-2">
            <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search...')" icon="magnifying-glass" clearable />
            <flux:button variant="primary" wire:click="create">{{ __('Add Item') }}</flux:button>
        </div>
    </div>

    <flux:table :paginate="$this->items">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'type'" :direction="$sortDirection" wire:click="sort('type')">{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Children') }}</flux:table.column>
            <flux:table.column>{{ __('Items') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->items as $item)
                <flux:table.row :key="$item->id">
                    <flux:table.cell variant="strong">
                        @if ($item->type !== \App\Enums\ItemType::Item)
                            <flux:link as="button" variant="ghost" wire:click="drillDown({{ $item->id }})" class="group">
                                <span>{{ $item->name }}</span>
                                <span class="invisible group-hover:visible">→</span>
                            </flux:link>
                        @else
                            {{ $item->name }}
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="match($item->type) { \App\Enums\ItemType::Location => 'blue', \App\Enums\ItemType::Bin => 'green', \App\Enums\ItemType::Item => 'zinc' }">
                            {{ ucfirst($item->type->value) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $item->children_count }}</flux:table.cell>
                    <flux:table.cell>{{ $item->all_items_count }}</flux:table.cell>
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
                    <flux:table.cell colspan="6" class="text-center">
                        <flux:text variant="subtle" size="xl">{{ __('No items found') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @teleport('body')
    <flux:modal name="item-form" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $form->editingItem ? __('Edit Item') : __('Add Item') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" type="text" required />

            <flux:select wire:model="form.type" :label="__('Type')">
                @foreach (\App\Enums\ItemType::cases() as $itemType)
                    <flux:select.option :value="$itemType->value">{{ ucfirst($itemType->value) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="form.parent_id" :label="__('Parent')">
                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                @foreach ($this->parentContainers as $parentContainer)
                    <flux:select.option :value="$parentContainer->id">{{ $parentContainer->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost" class="mr-2">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ $form->editingItem ? __('Update') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endteleport
</section>
