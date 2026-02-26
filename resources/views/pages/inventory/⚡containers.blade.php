<?php

use App\Livewire\Forms\Inventory\ContainerForm;
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

    public ContainerForm $form;

    #[Url]
    public ?int $parentId = null;

    public function drillDown(int $id): void
    {
        $this->parentId = $id;
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
            ->when($this->form->editingContainer, fn ($query) => $query->where('id', '!=', $this->form->editingContainer->id))
            ->orderBy('name')
            ->get();
    }

    public function create(): void
    {
        $this->form->fill(['parent_id' => $this->parentId]);
        $this->modal('container-form')->show();
    }

    public function edit(int $id): void
    {
        $this->form->load(
            Auth::user()->currentTeam->containers()->findOrFail($id)
        );
        $this->modal('container-form')->show();
    }

    public function save(): void
    {
        $this->form->save();

        $this->modal('container-form')->close();

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

        <flux:button variant="primary" size="sm" wire:click="create">{{ __('Add Container') }}</flux:button>
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
            @forelse ($this->containers as $container)
                <flux:table.row :key="$container->id">
                    <flux:table.cell variant="strong">
                        <flux:link as="button" variant="ghost" wire:click="drillDown({{ $container->id }})" class="group">
                            <span>{{ $container->name }}</span>
                            <span class="invisible group-hover:visible">→</span>
                        </flux:link>
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
            @empty
                <flux:table.row key="empty-container">
                    <flux:table.cell colspan="6" class="text-center">
                        <flux:text variant="subtle" size="xl">{{ __('No containers found') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @teleport('body')
    <flux:modal name="container-form" class="md:w-96">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $form->editingContainer ? __('Edit Container') : __('Add Container') }}</flux:heading>

            <flux:input wire:model="form.name" :label="__('Name')" type="text" required />

            <flux:select wire:model="form.type" :label="__('Type')">
                @foreach (\App\Enums\ContainerType::cases() as $containerType)
                    <flux:select.option :value="$containerType->value">{{ ucfirst($containerType->value) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="form.category" :label="__('Category')" type="text" />

            <flux:select wire:model="form.parent_id" :label="__('Parent Container')">
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
                <flux:button type="submit" variant="primary">{{ $form->editingContainer ? __('Update') : __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
    @endteleport
</section>
