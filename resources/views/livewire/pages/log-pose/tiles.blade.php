<flux:main class="space-y-6">
    <header class="flex">
        <flux:heading size="xl" level="1">{{ __('Tiles') }}</flux:heading>
        <flux:spacer />
        <flux:modal.trigger name="tile-form">
            <flux:button>Create</flux:button>
        </flux:modal.trigger>
    </header>

    <section>
        <div class="flex justify-between gap-8 mb-2">
            <flux:input size="sm" wire:model.live="search" icon="magnifying-glass" class="max-w-sm" placeholder="Search tiles" />

            <flux:select size="sm" wire:model.blur="perPage" class="max-w-20" placeholder="Per Page">
                <flux:option>5</flux:option>
                <flux:option>10</flux:option>
                <flux:option>25</flux:option>
                <flux:option>50</flux:option>
            </flux:select>
        </div>
        <flux:table :paginate="$this->tiles">
            <flux:columns>
                <flux:column>ID</flux:column>
                <flux:column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:column>
                <flux:column sortable :sorted="$sortBy === 'type'" :direction="$sortDirection" wire:click="sort('type')">Type</flux:column>
                <flux:column>Data</flux:column>
                <flux:column>Setting</flux:column>
            </flux:columns>

            <flux:rows>
                @foreach ($this->tiles as $tile)
                    <flux:row :key="$tile->id">
                        <flux:cell>{{ $tile->id }}</flux:cell>
                        <flux:cell>{{ $tile->name }}</flux:cell>
                        <flux:cell>{{ $tile->type }}</flux:cell>
                        <flux:cell>
                            <p class="text-ellipsis overflow-hidden max-w-64">
                                {{ json_encode($tile->data) }}
                            </p>
                        </flux:cell>
                        <flux:cell>
                            <p class="text-ellipsis overflow-hidden max-w-64">
                                {{ json_encode($tile->settings) }}
                            </p>
                        </flux:cell>

                        <flux:cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>

                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $tile->id }})">Edit</flux:menu.item>
                                    <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $tile->id }})">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:cell>
                    </flux:row>
                @endforeach
            </flux:rows>
        </flux:table>
    </section>

    <flux:modal name="tile-form" variant="flyout">
        <form wire:submit="save" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $this->editingTile ? 'Edit' : 'Create' }} Tile</flux:heading>
                <flux:subheading>Data for the Log Pose dashboard.</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" aria-autocomplete="none" />
            <flux:select wire:model.live="type" :label="__('Type')" placeholder="Select type">
                <flux:option value="calendar">Calendar</flux:option>
                <flux:option value="weather">Weather</flux:option>
                <flux:option value="coworkers">Coworkers</flux:option>
            </flux:select>

            @if ($type === 'calendar')
            <flux:input wire:model="settings.color" :label="__('Color')" />

            @foreach ($settings['links'] as $index => $link)
            <flux:input wire:model="settings.links.{{ $index }}" />
            @endforeach

            @elseif ($type === 'weather')
            <flux:input wire:model="settings.lat" :label="__('Latitude')" />
            <flux:input wire:model="settings.lon" :label="__('Longitude')" />
            @elseif ($type === 'coworkers')
            <flux:textarea wire:model="data" :label="__('Data')" />
            @endif

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:main>
