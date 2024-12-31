<flux:main class="space-y-6">
    <header class="flex">
        <flux:heading size="xl" level="1">{{ __('Recipes') }}</flux:heading>
        <flux:spacer />
        @auth
        <flux:button :href="route('cookbook.recipes.create')">Create</flux:button>
        @endauth
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
                        <flux:cell>
                            <flux:link variant="ghost" :href="route('cookbook.recipes.show', $recipe)">
                                {{ $recipe->name }}
                            </flux:link>
                        </flux:cell>
                        <flux:cell>{{ $recipe->total_time }}</flux:cell>
                        <flux:cell>{{ $recipe->categories }}</flux:cell>
                        <flux:cell>
                            @if (str_contains($recipe->source, 'http'))
                            <flux:link variant="ghost" :href="$recipe->source">
                                {{ $recipe->shortened_source }}
                            </flux:link>
                            @else
                            {{ $recipe->shortened_source }}
                            @endif
                        </flux:cell>

                        @auth
                        <flux:cell>
                            <flux:dropdown>
                                <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" inset="top bottom"></flux:button>

                                <flux:menu>
                                    <flux:menu.item icon="eye" :href="route('cookbook.recipes.show', $recipe)">View</flux:menu.item>
                                    <flux:menu.item icon="pencil-square" :href="route('cookbook.recipes.edit', $recipe)">Edit</flux:menu.item>
                                    <flux:menu.item variant="danger" icon="trash" wire:click="delete({{ $recipe->id }})">Delete</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:cell>
                        @endauth
                    </flux:row>
                @endforeach
            </flux:rows>
        </flux:table>
    </section>

    @guest
    <footer class="absolute bottom-0 left-0 w-full [grid-area:main] p-6 lg:p-8 [[data-flux-container]_&]:px-0  space-y-6">
        <flux:separator />
        <flux:subheading class="align-baseline text-center">
            Built with <flux:icon.heart class="inline" variant="mini" /> by
            <flux:link variant="ghost" href="https://techenby.com">
                Andy Newhouse
            </flux:link>
            using <flux:link variant="ghost" href="https://laravel.com/">Laravel</flux:link>,
            <flux:link variant="ghost" href="https://livewire.laravel.com/">Livewire</flux:link>,
            <flux:link variant="ghost" href="https://fluxui.dev/">Flux</flux:link>.
        </flux:subheading>
        <flux:subheading class="align-baseline text-center">
            Hosted on <flux:link variant="ghost" href="https://digitalocean.com">Digital Ocean</flux:link> via
            <flux:link variant="ghost" href="https://forge.laravel.com">Laravel Forge</flux:link>.
            View the sourcecode on
            <flux:link variant="ghost" href="https://github.com/techenby/sunny">GitHub</flux:link>.
        </flux:subheading>
    </footer>
    @endguest
</flux:main>
