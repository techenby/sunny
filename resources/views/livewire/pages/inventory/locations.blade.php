<flux:main class="space-y-6">
    <flux:heading size="xl" level="1">{{ __('Locations') }}</flux:heading>

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
</flux:main>
