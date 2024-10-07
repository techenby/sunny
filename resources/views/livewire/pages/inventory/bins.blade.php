<flux:main class="space-y-6">
    <header class="flex">
        <flux:heading size="xl" level="1">{{ __('Bins') }}</flux:heading>
        <flux:spacer />
        <flux:modal.trigger name="bin-form">
            <flux:button>Create</flux:button>
        </flux:modal.trigger>
    </header>

    <section>
        <div class="flex justify-between gap-8 mb-2">
            <flux:input size="sm" wire:model.live="search" icon="magnifying-glass" class="max-w-sm" placeholder="Search bins" />

            <flux:select size="sm" wire:model.blur="perPage" class="max-w-20" placeholder="Per Page">
                <flux:option>5</flux:option>
                <flux:option>10</flux:option>
                <flux:option>25</flux:option>
                <flux:option>50</flux:option>
            </flux:select>
        </div>
        <flux:table :paginate="$this->bins">
            <flux:columns>
                <flux:column>ID</flux:column>
                <flux:column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:column>
                <flux:column sortable :sorted="$sortBy === 'location_id'" :direction="$sortDirection" wire:click="sort('location_id')">Location</flux:column>
                <flux:column sortable :sorted="$sortBy === 'type'" :direction="$sortDirection" wire:click="sort('type')">Type</flux:column>
            </flux:columns>

            <flux:rows>
                @foreach ($this->bins as $bin)
                    <flux:row :key="$bin->id">
                        <flux:cell>{{ $bin->id }}</flux:cell>
                        <flux:cell>{{ $bin->name }}</flux:cell>
                        <flux:cell>{{ $bin->location->name ?? '-' }}</flux:cell>
                        <flux:cell>{{ $bin->type }}</flux:cell>

                        <flux:cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>

                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $bin->id }})">Edit</flux:menu.item>
                                    <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $bin->id }})">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:cell>
                    </flux:row>
                @endforeach
            </flux:rows>
        </flux:table>
    </section>

    <flux:modal name="bin-form" variant="flyout">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $this->editingBin ? 'Edit' : 'Create' }} Bin</flux:heading>
                <flux:subheading>A box or bookshelf that can store things in a location.</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" />
            <flux:input wire:model="location_id" :label="__('Location')" />
            <flux:input wire:model="type" :label="__('Type')" />

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:main>
