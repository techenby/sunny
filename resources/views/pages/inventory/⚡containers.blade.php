<?php

use App\Livewire\Traits\WithSorting;
use App\Livewire\Traits\WithSearching;
use App\Models\Container;
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

    #[Url]
    public ?int $parentId = null;

    public ?int $editingContainerId = null;

    public string $name = '';

    public string $type = 'location';

    public string $category = '';

    public mixed $containerId = null;

    public function drillDown(int $containerId): void
    {
        $this->parentId = $containerId;
        $this->resetPage();
    }

    public function navigateUp(): void
    {
        if ($this->parentId) {
            $parent = Container::query()
                ->where('id', $this->parentId)
                ->where('team_id', Auth::user()->currentTeam->id)
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
            ? Container::query()
                ->where('id', $this->parentId)
                ->where('team_id', Auth::user()->currentTeam->id)
                ->first()
            : null;

        while ($current) {
            $breadcrumbs->prepend($current);
            $current = $current->parent;
        }

        return $breadcrumbs;
    }

    #[Computed]
    public function containers(): LengthAwarePaginator
    {
        return Auth::user()->currentTeam->containers()
            ->when($this->parentId, fn ($query) => $query->where('parent_id', $this->parentId))
            ->when(! $this->parentId, fn ($query) => $query->whereNull('parent_id'))
            ->when($this->search, fn ($query) => $query->where('name', 'like', '%' . $this->search . '%'))
            ->withCount(['children', 'items'])
            ->orderBy($this->sortBy, $this->sortDirection)
            ->paginate(10);
    }

    #[Computed]
    public function parentContainers(): Collection
    {
        return Auth::user()->currentTeam->containers()
            ->when($this->editingContainerId, fn ($query) => $query->where('id', '!=', $this->editingContainerId))
            ->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        $this->editingContainerId = null;
        $this->reset('name', 'type', 'category', 'containerId');
        $this->containerId = $this->parentId;
        $this->modal('container-form')->show();
    }

    public function edit(int $id): void
    {
        $container = Auth::user()->currentTeam->containers()->findOrFail($id);

        $this->editingContainerId = $container->id;
        $this->name = $container->name;
        $this->type = $container->type->value;
        $this->category = $container->category ?? '';
        $this->containerId = $container->parent_id;

        $this->modal('container-form')->show();
    }

    public function save(): void
    {
        $this->containerId = $this->containerId ?: null;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:location,bin'],
            'category' => ['nullable', 'string', 'max:255'],
            'containerId' => ['nullable', 'integer', 'exists:containers,id'],
        ]);

        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'category' => $this->category ?: null,
            'parent_id' => $this->containerId,
        ];

        if ($this->editingContainerId) {
            Auth::user()->currentTeam->containers()
                ->where('id', $this->editingContainerId)
                ->firstOrFail()
                ->update($data);
        } else {
            Auth::user()->currentTeam->containers()->create($data);
        }

        $this->modal('container-form')->close();
        $this->reset('name', 'type', 'category', 'containerId', 'editingContainerId');

        unset($this->containers, $this->parentContainers);
    }

    public function delete(int $id): void
    {
        Auth::user()->currentTeam->containers()
            ->where('id', $id)
            ->firstOrFail()
            ->delete();

        unset($this->containers);
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

        <flux:button variant="primary" size="sm" wire:click="create">
            {{ __('Add Container') }}
        </flux:button>
    </div>

    <div class="mb-4">
        <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search containers...')" icon="magnifying-glass" />
    </div>

    <flux:table :paginate="$this->containers">
        <flux:table.columns>
            <flux:table.column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">{{ __('Name') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'type'" :direction="$sortDirection" wire:click="sort('type')">{{ __('Type') }}</flux:table.column>
            <flux:table.column sortable :sorted="$sortBy === 'category'" :direction="$sortDirection" wire:click="sort('category')">{{ __('Category') }}</flux:table.column>
            <flux:table.column>{{ __('Children') }}</flux:table.column>
            <flux:table.column>{{ __('Items') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @foreach ($this->containers as $container)
                <flux:table.row :key="$container->id">
                    <flux:table.cell variant="strong">
                        @if ($container->children_count > 0)
                            <flux:link as="button" variant="ghost" wire:click="drillDown({{ $container->id }})" class="group">
                                <span>{{ $container->name }}</span>
                                <span class="invisible group-hover:visible">→</span>
                            </flux:link>
                        @else
                            <span>{{ $container->name }}</span>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$container->type === \App\Enums\ContainerType::Location ? 'blue' : 'green'">
                            {{ ucfirst($container->type->value) }}
                        </flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>{{ $container->category ?? '—' }}</flux:table.cell>
                    <flux:table.cell>{{ $container->children_count }}</flux:table.cell>
                    <flux:table.cell>{{ $container->items_count }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />

                            <flux:menu>
                                <flux:menu.item wire:click="edit({{ $container->id }})" icon="pencil">{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.item wire:click="delete({{ $container->id }})" variant="danger" icon="trash" wire:confirm="{{ __('Are you sure you want to delete this container?') }}">{{ __('Delete') }}</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>

    @teleport('body')
    <flux:modal name="container-form" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingContainerId ? __('Edit Container') : __('Add Container') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" type="text" required />

            <flux:select wire:model="type" :label="__('Type')">
                @foreach (\App\Enums\ContainerType::cases() as $containerType)
                    <flux:select.option :value="$containerType->value">{{ ucfirst($containerType->value) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="category" :label="__('Category')" type="text" />

            <flux:select wire:model="containerId" :label="__('Parent Container')">
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
                <flux:button type="submit" variant="primary">{{ $editingContainerId ? __('Update') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endteleport
</section>
