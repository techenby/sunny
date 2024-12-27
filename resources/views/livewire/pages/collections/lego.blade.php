<flux:main class="space-y-6">
    <header class="flex">
        <flux:heading size="xl" level="1">{{ __('LEGO Collection') }}</flux:heading>
        <flux:spacer />
        <flux:modal.trigger name="bin-form">
            <flux:button>Create</flux:button>
        </flux:modal.trigger>
    </header>

    <section>
        <div class="flex justify-between gap-8 mb-2">
            <flux:input size="sm" wire:model.live="search" icon="magnifying-glass" class="max-w-sm"
                placeholder="Search Bins" />

            <flux:select size="sm" wire:model.blur="perPage" class="max-w-20" placeholder="Per Page">
                <flux:option>5</flux:option>
                <flux:option>10</flux:option>
                <flux:option>25</flux:option>
                <flux:option>50</flux:option>
            </flux:select>
        </div>

        <flux:table :paginate="$this->bins">
            <flux:columns>
                <flux:column sortable :sorted="$sortBy === 'type'" :direction="$sortDirection"
                    wire:click="sort('type')">Type</flux:column>
                <flux:column>Pieces</flux:column>
                <flux:column>Colors</flux:column>
            </flux:columns>

            <flux:rows>
                @foreach ($this->bins as $bin)
                    <flux:row :key="$bin->id">
                        <flux:cell>{{ $bin->type }}</flux:cell>
                        <flux:cell>
                            @foreach ($bin->pieces as $piece)
                            <flux:tooltip :content="$piece->name" :key="$bin->id . '-' . $piece->id">
                                <img src="{{ $piece->image }}" alt="">
                            </flux:tooltip>
                            @endforeach
                        </flux:cell>
                        <flux:cell>
                            <div class="isolate flex -space-x-2 overflow-hidden">
                                @foreach ($bin->colors as $color)
                                <flux:tooltip :content="$color->name" :key="$bin->id . '-' . $color->id">
                                    <div class="relative inline-block size-6 rounded-full ring-2 ring-white dark:ring-zinc-900" style="background: #{{ $color->hex }}"></div>
                                </flux:tooltip>
                                @endforeach
                            </div>
                        </flux:cell>

                        <flux:cell>
                            <flux:button wire:click="edit('{{ $bin->id }}')" variant="ghost" size="sm" icon="pencil" icon-variant="outline" inset="top bottom">
                            </flux:button>
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

            <flux:input wire:model="form.type" :label="__('Type')" aria-autocomplete="none" />

            <flux:select wire:model="form.pieces" :label="__('Pieces')" variant="listbox" searchable multiple
                placeholder="Choose pieces...">
                @foreach ($this->pieces as $piece)
                    <flux:option :value="$piece->id">
                        <div class="flex items-center gap-2">
                            <div class="w-20 shrink-0 align-middle">
                                <img src="{{ $piece->image }}" loading="lazy" alt="" class="max-w-48 max-h-16"
                                    style="zoom: 50%;">
                            </div>
                            <span>{{ $piece->name }}</span>
                        </div>
                    </flux:option>
                @endforeach
            </flux:select>

            <flux:select wire:model="form.colors" :label="__('Colors')" variant="listbox" searchable multiple
                placeholder="Choose colors...">
                @foreach ($this->colors as $color)
                    <flux:option :value="$color->id">
                        <div class="flex items-center gap-2">
                            <span class="size-4 rounded-full" style="background: #{{ $color->hex }}"></span>
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
