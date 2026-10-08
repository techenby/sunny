<flux:card body="flush" class="flex flex-col">
    <flux:card.header>
        <flux:card.heading size="lg">{{ __('Recently added recipes') }}</flux:card.heading>
        <flux:card.actions>
            <flux:button size="sm" variant="ghost" icon:trailing="arrow-right" :href="route('recipes.index')" wire:navigate>
                {{ __('All recipes') }}
            </flux:button>
        </flux:card.actions>
    </flux:card.header>

    <flux:card.body class="flex-1">
        @if ($this->recipes->isEmpty())
            <div class="py-6 text-center">
                <flux:icon name="book-open" class="mx-auto mb-3 size-10 text-zinc-400" />
                <flux:text>{{ __('Save your family\'s favorite recipes in one place.') }}</flux:text>
                <flux:button size="sm" class="mt-4" icon="plus" :href="route('recipes.create')" wire:navigate>
                    {{ __('Add a recipe') }}
                </flux:button>
            </div>
        @else
            <flux:card.bleed class="divide-y divide-zinc-900/5 dark:divide-white/10">
                @foreach ($this->recipes as $recipe)
                    <a
                        wire:key="dashboard-recipe-{{ $recipe->id }}"
                        href="{{ route('recipes.show', $recipe) }}"
                        wire:navigate
                        class="flex items-center gap-3 px-(--flux-bleed-x) py-3 hover:bg-zinc-900/2 dark:hover:bg-white/3"
                    >
                        <flux:avatar size="sm" :src="$recipe->photo_url" icon="book-open" color="amber" icon:variant="outline" />

                        <div class="min-w-0 flex-1">
                            <flux:heading class="truncate">{{ $recipe->name }}</flux:heading>
                            @if ($recipe->shortenedSource())
                                <flux:text size="sm" class="truncate">{{ $recipe->shortenedSource() }}</flux:text>
                            @endif
                        </div>

                        <flux:text size="sm" variant="subtle" class="shrink-0">{{ $recipe->created_at->diffForHumans(short: true) }}</flux:text>
                    </a>
                @endforeach
            </flux:card.bleed>
        @endif
    </flux:card.body>
</flux:card>
