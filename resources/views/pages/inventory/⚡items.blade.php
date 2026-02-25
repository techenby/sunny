<?php

use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component {
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $sortBy = 'name';

    public string $sortDirection = 'asc';

    public ?int $editingItemId = null;

    public string $name = '';

    public mixed $containerId = null;

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function items(): LengthAwarePaginator
    {
        return Auth::user()->currentTeam->items()
            ->with('container')
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
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

    public function createItem(): void
    {
        $this->editingItemId = null;
        $this->reset('name', 'containerId');
        $this->modal('item-form')->show();
    }

    public function editItem(int $id): void
    {
        $item = Auth::user()->currentTeam->items()->findOrFail($id);

        $this->editingItemId = $item->id;
        $this->name = $item->name;
        $this->containerId = $item->container_id;

        $this->modal('item-form')->show();
    }

    public function saveItem(): void
    {
        $this->containerId = $this->containerId ?: null;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'containerId' => ['nullable', 'integer', 'exists:containers,id'],
        ]);

        $data = [
            'name' => $this->name,
            'container_id' => $this->containerId,
        ];

        if ($this->editingItemId) {
            Auth::user()->currentTeam->items()
                ->where('id', $this->editingItemId)
                ->firstOrFail()
                ->update($data);
        } else {
            Auth::user()->currentTeam->items()->create($data);
        }

        $this->modal('item-form')->close();
        $this->reset('name', 'containerId', 'editingItemId');

        unset($this->items);
    }

    public function deleteItem(int $id): void
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
        <div></div>
        <flux:button variant="primary" size="sm" wire:click="createItem">
            {{ __('Add Item') }}
        </flux:button>
    </div>

    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search items...')" icon="magnifying-glass" />
    </div>

    <flux:table :paginate="$this->items">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Container') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->items as $item)
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
            @endforeach
        </flux:table.rows>
    </flux:table>

    @teleport('body')
    <flux:modal name="item-form" class="md:w-96">
        <form wire:submit="saveItem" class="space-y-6">
            <flux:heading size="lg">{{ $editingItemId ? __('Edit Item') : __('Add Item') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" type="text" required />

            <flux:select wire:model="containerId" :label="__('Container')">
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
                <flux:button type="submit" variant="primary">{{ $editingItemId ? __('Update') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endteleport
</section>
