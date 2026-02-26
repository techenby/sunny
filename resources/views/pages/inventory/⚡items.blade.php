<?php

use App\Livewire\Forms\Inventory\ItemForm;
use App\Livewire\Traits\WithSorting;
use App\Livewire\Traits\WithSearching;
use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;
    use WithSearching;
    use WithSorting;

    public ItemForm $form;

    #[Url(history: true)]
    public ?int $containerId = null;

    public function updatedContainerId(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return Auth::user()->currentTeam->items()
            ->with('container')
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->when($this->containerId, fn ($query) => $query->where('container_id', $this->containerId))
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }

    #[Computed]
    public function containers(): Collection
    {
        return Auth::user()->currentTeam->containers()
            ->orderBy('name')
            ->get();
    }

    public function create(): void
    {
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

        unset($this->items);
    }

    public function delete(int $id): void
    {
        Auth::user()->currentTeam->items()
            ->where('id', $id)
            ->firstOrFail()
            ->delete();

        unset($this->items);
    }

    public function containerPath(Item $item): string
    {
        if (! $item->container) {
            return '—';
        }

        $parts = collect();
        $current = $item->container;

        while ($current) {
            $parts->prepend($current->name);
            $current = $current->parent;
        }

        return $parts->implode(' / ');
    }
}; ?>

<section class="w-full">
    @include('pages.inventory.heading')

    <div class="mb-4 flex items-center justify-between gap-4">
        <div class="flex gap-2">
            <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search items...')" icon="magnifying-glass" />
            <flux:dropdown>
                <flux:button icon="funnel" square/>
                <flux:menu class="p-2">
                    <flux:select wire:model.live="containerId" :label="__('Filter by Container')">
                        <flux:select.option value="">{{ __('All Containers') }}</flux:select.option>
                        @foreach ($this->containers as $container)
                            <flux:select.option :value="$container->id">{{ $container->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:menu>
            </flux:dropdown>
        </div>
        <div class="flex gap-2">
            <flux:button variant="primary" wire:click="createItem">{{ __('Add Item') }}</flux:button>
        </div>
    </div>

    <flux:table :paginate="$this->items">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Container') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->items as $item)
                <flux:table.row :key="$item->id">
                    <flux:table.cell variant="strong">{{ $item->name }}</flux:table.cell>
                    <flux:table.cell>{{ $this->containerPath($item) }}</flux:table.cell>
                    <flux:table.cell>
                        <div class="flex justify-end gap-1">
                            <flux:button variant="ghost" size="sm" icon="pencil" wire:click="editItem({{ $item->id }})" />
                            <flux:button variant="ghost" size="sm" icon="trash" wire:click="deleteItem({{ $item->id }})" wire:confirm="{{ __('Are you sure you want to delete this item?') }}" />
                        </div>
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
        <form wire:submit="saveItem" class="space-y-6">
            <flux:heading size="lg">{{ $form->editingItem ? __('Edit Item') : __('Add Item') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" type="text" required />

            <flux:select wire:model="form.container_id" :label="__('Container')">
                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                @foreach ($this->containers as $container)
                    <flux:select.option :value="$container->id">{{ $container->name }}</flux:select.option>
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
