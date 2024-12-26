<flux:main class="space-y-6">
    <header class="flex">
        <flux:heading size="xl" level="1">{{ __('LEGO Collection') }}</flux:heading>
        <flux:spacer />
    </header>

    <section>
        <div class="flex justify-between gap-8 mb-2">
            <flux:input size="sm" wire:model.live="search" icon="magnifying-glass" class="max-w-sm"
                placeholder="Search Pieces" />

            <flux:select size="sm" wire:model.blur="perPage" class="max-w-20" placeholder="Per Page">
                <flux:option>5</flux:option>
                <flux:option>10</flux:option>
                <flux:option>25</flux:option>
                <flux:option>50</flux:option>
            </flux:select>
        </div>
        <flux:table :paginate="$this->pieces">
            <flux:columns>
                <flux:column>Piece</flux:column>
                <flux:column sortable :sorted="$sortBy === 'group_id'" :direction="$sortDirection"
                    wire:click="sort('group_id')">Group</flux:column>
                <flux:column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection"
                    wire:click="sort('name')">Name</flux:column>
                <flux:column sortable :sorted="$sortBy === 'part_number'" :direction="$sortDirection"
                    wire:click="sort('part_number')">Part #</flux:column>
            </flux:columns>

            <flux:rows>
                @foreach ($this->pieces as $piece)
                    <flux:row :key="$piece->id">
                        <flux:cell class="flex items-center gap-3">
                            <img src="{{ $piece->image }}" class="h-6">
                        </flux:cell>

                        <flux:cell>{{ $piece->group->name }}</flux:cell>
                        <flux:cell>{{ $piece->name }}</flux:cell>
                        <flux:cell>{{ $piece->part_number }}</flux:cell>

                        <flux:cell>
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom">
                            </flux:button>
                        </flux:cell>
                    </flux:row>
                @endforeach
            </flux:rows>
        </flux:table>
    </section>
</flux:main>
