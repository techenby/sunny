<?php

use App\Livewire\Forms\Inventory\ItemForm;
use App\Livewire\Traits\WithSearching;
use App\Livewire\Traits\WithSorting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Inventory')] class extends Component {
    use WithPagination;
    use WithSearching;
    use WithSorting;

    public ItemForm $form;

    #[Url]
    public $parentId = null;

    #[Computed]
    public function breadcrumbs(): BaseCollection
    {
        $breadcrumbs = collect();
        $current = $this->parentId
            ? Auth::user()->currentTeam->items()->find($this->parentId)
            : null;

        while ($current) {
            $breadcrumbs->prepend($current);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    #[Computed]
    public function items()
    {
        return Auth::user()->currentTeam
            ->items()
            ->withCount('children')
            ->where('parent_id', $this->parentId)
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->get();
    }

    #[Computed]
    public function parentItems(): Collection
    {
        return Auth::user()->currentTeam->items()
            ->when($this->form->editingItem, fn ($query) => $query->where('id', '!=', $this->form->editingItem->id))
            ->orderBy('name')
            ->get();
    }

    public function delete(int $id): void
    {
        $item = $this->items->firstWhere('id', $id);
        throw_if($item === null, ModelNotFoundException::class);

        $item->delete();
        unset($this->items, $this->parentItems);
    }

    public function edit(int $id): void
    {
        $item = $this->items->firstWhere('id', $id);
        throw_if($item === null, ModelNotFoundException::class);

        $this->form->load($item);
        $this->modal('item-form')->show();
    }

    public function navigateDown(int $id): void
    {
        $this->parentId = $id;
        unset($this->items, $this->parentItems, $this->breadcrumbs);
    }

    public function navigateUp(): void
    {
        if ($this->parentId) {
            $parent = Auth::user()->currentTeam->items()->find($this->parentId);
            $this->parentId = $parent?->parent_id;
            unset($this->items, $this->parentItems, $this->breadcrumbs);
        }
    }

    public function save(): void
    {
        $this->form->save();
        $this->modal('item-form')->close();
        unset($this->items, $this->parentItem);
    }
};
?>

<section class="w-full">
    <div class="mb-4 flex items-center justify-between">
        <flux:heading size="xl">{{ __('Inventory') }}</flux:heading>
        <flux:modal.trigger name="item-form">
            <flux:button variant="primary">{{ __('Add Item') }}</flux:button>
        </flux:modal.trigger>
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

    <flux:table>
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Modified') }}</flux:table.column>
            <flux:table.column>{{ __('Size') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->items as $item)
                <flux:table.row :key="$item->id">
                    <flux:table.cell>
                        <flux:link wire:click="navigateDown({{ $item->id }})" inset="top bottom" class="!flex !items-center gap-3">
                            <flux:avatar size="xs" :icon="$item->type->getIcon()" :color="$item->type->getIconColor()" icon:variant="outline" />
                            <span>{{ $item->name }}</span>
                        </flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $item->updated_at }}</flux:table.cell>
                    <flux:table.cell>
                        {{ $item->children_count }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />

                            <flux:menu>
                                <flux:menu.item wire:click="edit({{ $item->id }})" icon="pencil" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item wire:click="delete({{ $item->id }})" variant="danger" icon="trash" wire:confirm="{{ __('Are you sure you want to delete this recipe?') }}">{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row key="empty-recipe">
                    <flux:table.cell colspan="4" class="text-center">
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
                @foreach (\App\Enums\ItemType::cases() as $type)
                    <flux:select.option :value="$type->value">{{ ucfirst($type->value) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="form.parent_id" :label="__('Parent Container')">
                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                @foreach ($this->parentItems as $parentItem)
                    <flux:select.option :value="$parentItem->id">{{ $parentItem->name }}</flux:select.option>
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
