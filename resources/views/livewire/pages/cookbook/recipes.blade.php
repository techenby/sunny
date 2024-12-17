<flux:main class="space-y-6">
    <header class="flex">
        <flux:heading size="xl" level="1">{{ __('Recipes') }}</flux:heading>
        <flux:spacer />
        <flux:modal.trigger name="recipe-form">
            <flux:button>Create</flux:button>
        </flux:modal.trigger>
    </header>

    <section>
        <div class="flex justify-between gap-8 mb-2">
            <flux:input size="sm" wire:model.live="search" icon="magnifying-glass" class="max-w-sm" placeholder="Search Recipes" />

            <flux:select size="sm" wire:model.blur="perPage" class="max-w-20" placeholder="Per Page">
                <flux:option>5</flux:option>
                <flux:option>10</flux:option>
                <flux:option>25</flux:option>
                <flux:option>50</flux:option>
            </flux:select>
        </div>
        <flux:table :paginate="$this->recipes">
            <flux:columns>
                <flux:column sortable :sorted="$sortBy === 'name'" :direction="$sortDirection" wire:click="sort('name')">Name</flux:column>
                <flux:column sortable :sorted="$sortBy === 'total_time'" :direction="$sortDirection" wire:click="sort('total_time')">Total Time</flux:column>
                <flux:column sortable :sorted="$sortBy === 'categories'" :direction="$sortDirection" wire:click="sort('categories')">Categories</flux:column>
                <flux:column sortable :sorted="$sortBy === 'source'" :direction="$sortDirection" wire:click="sort('source')">Source</flux:column>
            </flux:columns>

            <flux:rows>
                @foreach ($this->recipes as $recipe)
                    <flux:row :key="$recipe->id">
                        <flux:cell>{{ $recipe->name }}</flux:cell>
                        <flux:cell>{{ $recipe->total_time }}</flux:cell>
                        <flux:cell>{{ $recipe->categories }}</flux:cell>
                        <flux:cell>{{ $recipe->source }}</flux:cell>

                        <flux:cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>

                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $recipe->id }})">Edit</flux:menu.item>
                                    <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $recipe->id }})">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:cell>
                    </flux:row>
                @endforeach
            </flux:rows>
        </flux:table>
    </section>
</flux:main>
