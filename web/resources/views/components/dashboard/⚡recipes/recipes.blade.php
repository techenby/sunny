<x-dashboard.card :heading="__('Recently added recipes')" :action="__('All recipes')" :href="route('recipes.index')">
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
                <x-dashboard.row
                    wire:key="dashboard-recipe-{{ $recipe->id }}"
                    :href="route('recipes.show', $recipe)"
                    :heading="$recipe->name"
                    :subheading="$recipe->shortenedSource()"
                    :meta="$recipe->created_at->diffForHumans(short: true)"
                >
                    <x-slot:avatar>
                        <flux:avatar size="sm" :src="$recipe->thumb_url" icon="book-open" color="amber" icon:variant="outline" />
                    </x-slot:avatar>
                </x-dashboard.row>
            @endforeach
        </flux:card.bleed>
    @endif
</x-dashboard.card>
