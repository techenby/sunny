<flux:main class="space-y-6">
    <div class="flex">
        <flux:heading size="xl" level="1">{{ __('Locations') }}</flux:heading>
        <flux:spacer />
        <flux:modal.trigger name="create-location">
            <flux:button>Create</flux:button>
        </flux:modal.trigger>
    </div>

    <flux:table :paginate="$this->locations">
        <flux:columns>
            <flux:column>ID</flux:column>
            <flux:column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:column>
        </flux:columns>

        <flux:rows>
            @foreach ($this->locations as $location)
                <flux:row :key="$location->id">
                    <flux:cell>{{ $location->id }}</flux:cell>
                    <flux:cell>{{ $location->name }}</flux:cell>

                    <flux:cell>
                        <flux:dropdown>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>

                            <flux:menu>
                                <flux:menu.item icon="pencil-square" wire:click="edit({{ $location->id }})">Edit</flux:menu.item>
                                <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $location->id }})">Delete</flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </flux:cell>
                </flux:row>
            @endforeach
        </flux:rows>
    </flux:table>

    <flux:modal name="create-location" variant="flyout">
        <form wire:submit="store" class="space-y-6">
            <div>
                <flux:heading size="lg">Create Location</flux:heading>
                <flux:subheading>A place or room that can store bins and things.</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" />

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:main>
