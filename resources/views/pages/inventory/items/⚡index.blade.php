<?php

use App\Livewire\Traits\WithSorting;
use App\Livewire\Traits\WithSearching;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;
    use WithSearching;
    use WithSorting;

    public int $parentId;

    public array $filters = [
        'type' => 'all'
    ];

    #[Computed]
    public function items()
    {
        return Auth::user()->currentTeam->items()
            ->tap(fn ($query) => $this->search ? $query->whereLike('name', '%' . $this->search . '%') : $query)
            ->tap(fn ($query) => $this->filters['type'] !== 'all' ? $query->whereType($this->filters['type']) : $query)
            ->tap(fn ($query) => $this->sortBy ? $query->orderBy($this->sortBy, $this->sortDirection) : $query)
            ->where('parent_id', $this->parentId ?? null)
            ->paginate(10);
    }

    public function open(int $id): void
    {
        $this->parentId = $id;
    }
};
?>

<x-slot:breadcrumbs>
    <flux:breadcrumbs>
        <flux:breadcrumbs.item :href="route('dashboard')" icon="home" />
        <flux:breadcrumbs.item :href="route('inventory.index')">Inventory</flux:breadcrumbs.item>
        <flux:breadcrumbs.item>Items</flux:breadcrumbs.item>
    </flux:breadcrumbs>
</x-slot:breadcrumbs>

<div>
    <flux:heading level="1" size="xl">Items</flux:heading>

    <div class="flex mb-2 mt-8">
        <div class="flex items-center gap-2">
            <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search...')" icon="magnifying-glass" clearable />
            <flux:dropdown>
                <flux:button icon="funnel" icon:variant="outline" square/>

                <flux:menu>
                    <flux:select wire:model.live="filters.type" label="Type">
                        <flux:select.option value="all">All</flux:select.option>
                        @foreach (App\Enums\ItemType::cases() as $type)
                            <flux:select.option :value="$type">{{ ucfirst($type->value) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:menu>
            </flux:dropdown>
        </div>

        <flux:spacer />
        <flux:button variant="primary" icon="plus">Add Item</flux:button>
    </div>

    <flux:table :paginate="$this->items">
        <flux:table.columns>
            <flux:table.column class="w-8"><flux:checkbox.all /></flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:table.column>
            <flux:table.column>Modified</flux:table.column>
            <flux:table.column>
                <span class="sr-only">Actions</span>
            </flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->items as $item)
                <flux:table.row :key="$item->id">
                    <flux:table.cell class="group">
                        <flux:checkbox />
                    </flux:table.cell>
                    <flux:table.cell class="flex items-center gap-2">
                        <x-item-icon :type="$item->type"/>
                        @if ($item->children->isEmpty())
                        <span>{{ $item->name }}</span>
                        @else
                        <flux:link wire:click="open({{ $item->id }})">{{ $item->name }}</flux:link>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        {{ $item->updated_at->toFormattedDateString() }}
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:dropdown>
                            <flux:button icon="ellipsis-vertical" variant="ghost" size="sm" inset="top bottom" />

                            <flux:menu>
                                <flux:menu.item>Edit</flux:menu.item>
                                <flux:menu.item variant="danger" icon="trash">Delete</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
