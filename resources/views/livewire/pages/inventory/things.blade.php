<flux:main class="space-y-6">
    <header class="flex">
        <flux:heading size="xl" level="1">{{ __('Things') }}</flux:heading>
        <flux:spacer />
        <flux:modal.trigger name="thing-form">
            <flux:button>Create</flux:button>
        </flux:modal.trigger>
    </header>

    <section>
        <div class="flex justify-between gap-8 mb-2">
            <flux:input size="sm" wire:model.live="search" icon="magnifying-glass" class="max-w-sm" placeholder="Search things" />

            <flux:select size="sm" wire:model.blur="perPage" class="max-w-20" placeholder="Per Page">
                <flux:option>5</flux:option>
                <flux:option>10</flux:option>
                <flux:option>25</flux:option>
                <flux:option>50</flux:option>
            </flux:select>
        </div>
        <flux:table :paginate="$this->things">
            <flux:columns>
                <flux:column>ID</flux:column>
                <flux:column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:column>
                <flux:column sortable :sorted="$sortBy === 'bin_id'" :direction="$sortDirection" wire:click="sort('bin_id')">Bin</flux:column>
                <flux:column sortable :sorted="$sortBy === 'location_id'" :direction="$sortDirection" wire:click="sort('location_id')">Location</flux:column>
            </flux:columns>

            <flux:rows>
                @foreach ($this->things as $thing)
                    <flux:row :key="$thing->id">
                        <flux:cell>{{ $thing->id }}</flux:cell>
                        <flux:cell>{{ $thing->name }}</flux:cell>
                        <flux:cell>{{ $thing->bin->name ?? '-' }}</flux:cell>
                        <flux:cell>{{ $thing->location->name ?? '-' }}</flux:cell>

                        <flux:cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>

                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $thing->id }})">Edit</flux:menu.item>
                                    <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $thing->id }})">Delete</flux:menu.item>
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
                <flux:heading size="lg">{{ $this->editingThing ? 'Edit' : 'Create' }} Thing</flux:heading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" aria-autocomplete="none" />

            <flux:select wire:model="bin_id" :label="__('Bin')" placeholder="Choose bin...">
                @foreach ($bins as $id => $label)
                    <flux:option wire:key="{{ $id }}" value="{{ $id }}">{{ $label }}</flux:option>
                @endforeach
            </flux:select>

            <flux:select wire:model="location_id" :label="__('Location')" placeholder="Choose location...">
                @foreach ($locations as $id => $label)
                    <flux:option wire:key="{{ $id }}" value="{{ $id }}">{{ $label }}</flux:option>
                @endforeach
            </flux:select>

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:main>
