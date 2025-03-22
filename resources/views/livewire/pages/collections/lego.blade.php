<flux:main class="space-y-6">
    <header class="flex">
        <flux:heading size="xl" level="1">{{ __('LEGO Collection') }}</flux:heading>
        <flux:spacer />
        <flux:modal.trigger name="bin-form">
            <flux:button>Create</flux:button>
        </flux:modal.trigger>
    </header>

    <section x-data="{showFilters: false}" class="space-y-3">
        <div class="flex gap-4">
            <flux:input size="sm" wire:model.live="search" icon="magnifying-glass" class="max-w-sm"
                placeholder="Search Bins" />
            <flux:spacer />
            <flux:button @click="showFilters = !showFilters" size="sm">Filter</flux:button>
            <flux:select size="sm" wire:model.blur-sm="perPage" class="max-w-20" placeholder="Per Page">
                <flux:option>5</flux:option>
                <flux:option>10</flux:option>
                <flux:option>25</flux:option>
                <flux:option>50</flux:option>
            </flux:select>
        </div>

        <flux:card x-show="showFilters" x-cloak class="grid grid-cols-2 gap-4">
            <flux:select wire:model.live="filter.part" :label="__('Part')" variant="listbox" searchable clearable
                placeholder="Choose part...">
                @foreach ($this->filterParts as $part)
                    <flux:option :value="$part->id">
                        <div class="flex items-center gap-2">
                            <x-lego.part :$part />
                            <span>{{ $part->name }}</span>
                        </div>
                    </flux:option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="filter.color" :label="__('Color')" variant="listbox" searchable clearable
                placeholder="Choose color...">
                @foreach ($this->filterColors as $color)
                    <flux:option :value="$color->id">
                        <div class="flex items-center gap-2">
                            <x-lego.color :$color size="size-4" />
                            <span>{{ $color->name }}</span>
                        </div>
                    </flux:option>
                @endforeach
            </flux:select>
        </flux:card>

        <flux:table :paginate="$this->bins">
            <flux:columns>
                <flux:column sortable :sorted="$sortBy === 'type'" :direction="$sortDirection"
                    wire:click="sort('type')">Type</flux:column>
                <flux:column>Parts</flux:column>
                <flux:column>Colors</flux:column>
            </flux:columns>

            <flux:rows>
                @foreach ($this->bins as $bin)
                    <flux:row :key="$bin->id">
                        <flux:cell>{{ $bin->type }}</flux:cell>
                        <flux:cell>
                            @foreach ($bin->parts as $part)
                            <flux:tooltip :content="$part->name" :key="$bin->id . '-' . $part->id">
                                <x-lego.part :$part />
                            </flux:tooltip>
                            @endforeach
                        </flux:cell>
                        <flux:cell>
                            <div class="isolate flex -space-x-2 overflow-hidden">
                                @foreach ($bin->colors as $color)
                                <flux:tooltip :content="$color->name" :key="$bin->id . '-' . $color->id">
                                    <x-lego.color :$color />
                                </flux:tooltip>
                                @endforeach
                            </div>
                        </flux:cell>

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
                <flux:heading size="lg">Bin</flux:heading>
            </div>

            <flux:autocomplete wire:model="form.type" :label="__('Type')">
                <flux:autocomplete.item>Gridfinity 1×1</flux:autocomplete.item>
                <flux:autocomplete.item>Gridfinity 1×2</flux:autocomplete.item>
                <flux:autocomplete.item>Gridfinity 1×3</flux:autocomplete.item>
                <flux:autocomplete.item>Gridfinity 2×2</flux:autocomplete.item>
                <flux:autocomplete.item>Bag</flux:autocomplete.item>
                <flux:autocomplete.item>Bin</flux:autocomplete.item>
                <flux:autocomplete.item>Drawer</flux:autocomplete.item>
            </flux:autocomplete>

            <flux:select wire:model="form.parts" :label="__('Parts')" variant="listbox" searchable multiple :filter="false" placeholder="Choose parts...">
                <x-slot name="search">
                    <flux:select.search wire:model.live="partKeyword" class="px-4" placeholder="Search parts..." />
                </x-slot>

                @foreach ($this->parts as $part)
                    <flux:option :value="$part->id">
                        <div class="flex items-center gap-2">
                            <x-lego.part :$part />
                            <span>{{ $part->name }}</span>
                        </div>
                    </flux:option>
                @endforeach
            </flux:select>

            <flux:select wire:model="form.colors" :label="__('Colors')" variant="listbox" searchable multiple :filter="false"
                placeholder="Choose colors...">
                <x-slot name="search">
                    <flux:select.search wire:model.live="colorKeyword" class="px-4" placeholder="Search colors..." />
                </x-slot>

                @foreach ($this->colors as $color)
                    <flux:option :value="$color->id">
                        <div class="flex items-center gap-2">
                            <x-lego.color :$color size="size-4" />
                            <span>{{ $color->name }}</span>
                        </div>
                    </flux:option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="form.notes" :label="__('Notes')" />

            <div class="flex">
                <flux:spacer />

                <flux:button type="submit" variant="primary">Save changes</flux:button>
            </div>
        </form>
    </flux:modal>
</flux:main>
