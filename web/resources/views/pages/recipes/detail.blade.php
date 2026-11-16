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

        @if ($recipe->ingredients || $editable)
            <flux:card>
                <flux:heading size="lg" class="mb-2 text-lg!">{{ __('Ingredients') }}</flux:heading>
                @if ($recipe->ingredients)
                    <x-prose>
                        @foreach (RecipeLines::groups($recipe->ingredients) as $group)
                            @if ($group['heading'])
                                <h4>{{ $group['heading'] }}</h4>
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

        @if ($recipe->instructions || $editable)
            <flux:card>
                <flux:heading size="lg" class="mb-2 text-lg!">{{ __('Instructions') }}</flux:heading>
                @if ($recipe->instructions)
                    <x-prose>
                        @foreach (RecipeLines::groups($recipe->instructions) as $group)
                            @if ($group['heading'])
                                <h4>{{ $group['heading'] }}</h4>
                            @endif
                            @if ($group['rows'])
                                <ol start="{{ $group['start'] }}">
                                    @foreach ($group['rows'] as $row)
                                        <li>{{ $row['text'] }}</li>
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
        <flux:card>
            <flux:heading size="lg" class="mb-4 text-lg!">{{ __('Details') }}</flux:heading>

            <dl class="space-y-4">
                @if ($recipe->source)
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Source') }}</dt>
                        <dd class="mt-1 text-zinc-800 dark:text-white">
                            @if ($recipe->isSourceUrl())
                                <flux:link href="{{ $recipe->source }}" target="_blank">
                                    {{ $recipe->shortenedSource() }}
                                </flux:link>
                            @else
                                {{ $recipe->source }}
                            @endif
                        </dd>
                    </div>
                @endif

                @if ($recipe->servings)
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Servings') }}</dt>
                        <dd class="mt-1 text-zinc-800 dark:text-white">{{ $recipe->servings }}</dd>
                    </div>
                @endif

                @if ($recipe->prep_time)
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Prep time') }}</dt>
                        <dd class="mt-1 text-zinc-800 dark:text-white">{{ $recipe->prep_time }}</dd>
                    </div>
                @endif

                @if ($recipe->cook_time)
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Cook time') }}</dt>
                        <dd class="mt-1 text-zinc-800 dark:text-white">{{ $recipe->cook_time }}</dd>
                    </div>
                @endif

                @if ($recipe->total_time)
                    <div>
                        <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total time') }}</dt>
                        <dd class="mt-1 text-zinc-800 dark:text-white">{{ $recipe->total_time }}</dd>
                    </div>
                @endif
            </dl>
        </flux:card>

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
            <flux:heading size="lg" class="mb-2 text-lg!">{{ __('Remixes') }}</flux:heading>

            @if ($remixes->isEmpty())
                <flux:text>{{ __('No remixes yet') }}</flux:text>
            @else
                <ul class="space-y-3">
                    @foreach ($remixes as $remix)
                        @php($changedFields = $remix->changedFieldsFrom($recipe))
                        <li>
                            <flux:link href="{{ route('recipes.show', $remix) }}" wire:navigate>
                                {{ $remix->name }}
                            </flux:link>
                            <flux:text class="mt-0.5">
                                {{ $remix->team->name }}
                                &middot;
                                {{ $changedFields ? __('Changed :fields', ['fields' => Arr::join($changedFields, ', ', ' and ')]) : __('No changes yet') }}
                                &middot;
                                <time datetime="{{ $remix->created_at->toIso8601String() }}">{{ $remix->created_at->diffForHumans() }}</time>
                            </flux:text>
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>
    </div>
</div>
