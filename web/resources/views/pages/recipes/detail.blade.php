@use('App\Support\Recipes\RecipeLines')

<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        @php
            $editable ??= false;
            $editUrl = $editable ? route('recipes.edit', $recipe) : null;
            $emptyTile = 'bg-amber-200 text-amber-800 dark:bg-amber-400/40 dark:text-amber-200';
        @endphp

        @if ($recipe->photo_path)
            <img src="{{ Storage::temporaryUrl($recipe->photo_path, now()->addMinutes(30)) }}" alt="{{ $recipe->name }}" class="w-full rounded-lg object-cover max-h-96" />
        @elseif ($editable)
            <a href="{{ $editUrl }}" wire:navigate class="flex h-48 w-full flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-zinc-300 text-zinc-500 transition hover:border-zinc-400 hover:text-zinc-800 dark:border-white/20 dark:text-white/70 dark:hover:border-white/40 dark:hover:text-white">
                <flux:icon.photo class="size-6" />
                <span class="text-sm font-medium">{{ __('Upload a photo') }}</span>
            </a>
        @endif

        @if ($recipe->description || $editable)
            <flux:card>
                <flux:heading size="lg" class="mb-2 text-lg!">{{ __('Description') }}</flux:heading>
                @if ($recipe->description)
                    <flux:text size="lg" class="leading-7">{{ $recipe->description }}</flux:text>
                @else
                    <x-dashboard.empty
                        icon="document-text"
                        :tile="$emptyTile"
                        :heading="__('No description yet')"
                        :text="__('Say what makes this one worth cooking.')"
                        :action="__('Add a description')"
                        :href="$editUrl"
                    />
                @endif

                @if ($recipe->tags)
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($recipe->tags as $tag)
                            <flux:badge size="sm">{{ $tag }}</flux:badge>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        @endif

        @if ($recipe->instructions || $editable)
            <flux:card>
                <div class="mb-2 flex items-baseline justify-between gap-2">
                    <flux:heading size="lg" class="text-lg!">{{ __('Instructions') }}</flux:heading>
                    @if ($stepCount = collect($recipe->instructions)->where('type', '!=', 'heading')->count())
                        <flux:text size="sm" variant="subtle">{{ trans_choice(':count step|:count steps', $stepCount) }}</flux:text>
                    @endif
                </div>
                @if ($recipe->instructions)
                    <x-prose class="max-w-none [&>ol]:flex [&>ol]:list-none [&>ol]:flex-col [&>ol]:gap-3 [&>ol]:pl-0 [&>ol>li]:relative [&>ol>li]:pl-10 [&>ol>li]:before:absolute [&>ol>li]:before:left-0 [&>ol>li]:before:top-0 [&>ol>li]:before:flex [&>ol>li]:before:size-7 [&>ol>li]:before:items-center [&>ol>li]:before:justify-center [&>ol>li]:before:rounded-full [&>ol>li]:before:border [&>ol>li]:before:border-zinc-200 [&>ol>li]:before:text-sm [&>ol>li]:before:font-medium [&>ol>li]:before:text-zinc-800 [&>ol>li]:before:content-[attr(data-step)] dark:[&>ol>li]:before:border-white/20 dark:[&>ol>li]:before:text-white">
                        @foreach (RecipeLines::groups($recipe->instructions) as $group)
                            @if ($group['heading'])
                                <h4>{{ $group['heading'] }}</h4>
                            @endif
                            @if ($group['rows'])
                                <ol start="{{ $group['start'] }}">
                                    @foreach ($group['rows'] as $row)
                                        <li data-step="{{ $group['start'] + $loop->index }}">{{ $row['text'] }}</li>
                                    @endforeach
                                </ol>
                            @endif
                        @endforeach
                    </x-prose>
                @else
                    <x-dashboard.empty
                        icon="numbered-list"
                        :tile="$emptyTile"
                        :heading="__('No instructions yet')"
                        :text="__('Write the steps, or paste them from wherever you found the recipe.')"
                        :action="__('Add steps')"
                        :href="$editUrl"
                    >
                        <flux:link :href="$editUrl" wire:navigate class="text-sm">{{ __('Paste from text') }}</flux:link>
                    </x-dashboard.empty>
                @endif
            </flux:card>
        @endif

        @if ($recipe->notes)
            <flux:card>
                <flux:heading size="lg" class="mb-2 text-lg!">{{ __('Notes') }}</flux:heading>
                <flux:text class="whitespace-pre-line">{{ $recipe->notes }}</flux:text>
            </flux:card>
        @endif
    </div>

    <div class="space-y-6">
        @if ($recipe->ingredients || $editable)
            <flux:card>
                <div class="mb-2 flex items-baseline justify-between gap-2">
                    <flux:heading size="lg" class="text-lg!">{{ __('Ingredients') }}</flux:heading>
                    @if ($ingredientCount = collect($recipe->ingredients)->where('type', '!=', 'heading')->count())
                        <flux:text size="sm" variant="subtle">{{ trans_choice(':count ingredient|:count ingredients', $ingredientCount) }}</flux:text>
                    @endif
                </div>
                @if ($recipe->ingredients)
                    <x-prose>
                        @foreach (RecipeLines::groups($recipe->ingredients) as $group)
                            @if ($group['heading'])
                                @php
                                    preg_match('/^(.*?)\s*\((Step \d+)\)$/i', $group['heading'], $tagged);
                                @endphp
                                <h4 class="flex items-baseline gap-2 border-b border-zinc-100 pb-1 dark:border-white/10">
                                    {{ $tagged[1] ?? $group['heading'] }}
                                    @isset($tagged[2])
                                        <span class="text-xs font-normal text-zinc-400">{{ $tagged[2] }}</span>
                                    @endisset
                                </h4>
                            @endif
                            @if ($group['rows'])
                                <ul>
                                    @foreach ($group['rows'] as $row)
                                        <li>{{ $row['text'] }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        @endforeach
                    </x-prose>
                @else
                    <x-dashboard.empty
                        icon="list-bullet"
                        :tile="$emptyTile"
                        :heading="__('No ingredients yet')"
                        :text="__('List what goes in, one ingredient per line.')"
                        :action="__('Add ingredients')"
                        :href="$editUrl"
                    />
                @endif
            </flux:card>
        @endif

        @if ($recipe->nutrition)
            <flux:card>
                <flux:heading size="lg" class="mb-2 text-lg!">{{ __('Nutrition') }}</flux:heading>
                <div class="prose dark:prose-invert">
                    {!! nl2br(e($recipe->nutrition)) !!}
                </div>
            </flux:card>
        @endif

        @if ($recipe->parent)
            <flux:card>
                <flux:heading size="lg" class="mb-2 text-lg!">{{ __('Remixed from') }}</flux:heading>
                <flux:link href="{{ route('recipes.show', $recipe->parent) }}" wire:navigate>
                    {{ $recipe->parent->name }}
                </flux:link>
            </flux:card>
        @endif

        @php
            $remixes = $recipe->remixes()
                ->whereKeyNot($recipe->getKey())
                ->with('team')
                ->latest()
                ->get()
                ->filter(fn ($remix) => Auth::user()?->can('view', $remix));
        @endphp

        <flux:card>
            <div class="mb-3 flex items-baseline justify-between gap-2">
                <flux:heading size="lg" class="text-lg!">{{ __('Remixes') }}</flux:heading>
                @if ($remixes->isNotEmpty())
                    <flux:text size="sm" variant="subtle">{{ $remixes->count() }}</flux:text>
                @endif
            </div>

            @if ($remixes->isEmpty())
                <flux:text>{{ __('No remixes yet') }}</flux:text>
            @else
                <ul class="space-y-3">
                    @foreach ($remixes as $remix)
                        <li>
                            <a href="{{ route('recipes.show', $remix) }}" wire:navigate class="group flex items-start gap-3">
                                <x-ui.table.thumb icon="arrow-path-rounded-square" size="base" />
                                <div class="min-w-0 text-sm">
                                    <div class="font-medium text-zinc-800 group-hover:text-(--color-accent-content) dark:text-white">{{ $remix->name }}</div>
                                    <flux:text variant="subtle">
                                        <time datetime="{{ $remix->created_at->toIso8601String() }}">{{ $remix->created_at->diffForHumans() }}</time>
                                    </flux:text>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>
    </div>
</div>
