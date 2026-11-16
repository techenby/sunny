<x-dashboard.card :heading="__('Recently added recipes')" :action="__('All recipes')" :href="route('recipes.index')">
    @if ($this->recipes->isEmpty())
        <x-dashboard.empty
            icon="book-open"
            tile="bg-amber-200 text-amber-800 dark:bg-amber-400/40 dark:text-amber-200"
            :heading="__('No recipes yet')"
            :text="__('Save one from a link or type it in, and the newest show up here.')"
            :action="__('Add a recipe')"
            :href="route('recipes.create')"
        />
    @else
        <flux:card.bleed class="divide-y divide-zinc-900/5 dark:divide-white/10">
            @foreach ($this->recipes as $recipe)
                <x-dashboard.row
                    wire:key="dashboard-recipe-{{ $recipe->id }}"
                    :href="route('recipes.show', $recipe)"
                    :heading="$recipe->name"
                    :subheading="$recipe->shortenedSource()"
                    :meta="$recipe->created_at->diffForHumans()"
                    :src="$recipe->thumb_url"
                    icon="book-open"
                    tile="bg-amber-200 text-amber-800 dark:bg-amber-400/40 dark:text-amber-200"
                />
            @endforeach
        </flux:card.bleed>
    @endif
</x-dashboard.card>
