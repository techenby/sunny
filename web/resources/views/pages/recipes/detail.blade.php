<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        @if ($recipe->photo_path)
            <img src="{{ Storage::temporaryUrl($recipe->photo_path, now()->addMinutes(30)) }}" alt="{{ $recipe->name }}" class="w-full rounded-lg object-cover max-h-96" />
        @endif
        @if ($recipe->description)
            <flux:card>
                <flux:heading size="lg" class="mb-2 text-lg!">{{ __('Description') }}</flux:heading>
                <flux:text size="lg" class="leading-7">{{ $recipe->description }}</flux:text>

                @if ($recipe->tags)
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($recipe->tags as $tag)
                            <flux:badge size="sm">{{ $tag }}</flux:badge>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        @endif

        @if ($recipe->ingredients)
            <flux:card>
                <flux:heading size="lg" class="mb-2 text-lg!">{{ __('Ingredients') }}</flux:heading>
                <x-prose :content="$recipe->ingredients" />
            </flux:card>
        @endif

        @if ($recipe->instructions)
            <flux:card>
                <flux:heading size="lg" class="mb-2 text-lg!">{{ __('Instructions') }}</flux:heading>
                <x-prose :content="$recipe->instructions" />
            </flux:card>
        @endif

        @if ($recipe->notes)
            <flux:card>
                <flux:heading size="lg" class="mb-2 text-lg!">{{ __('Notes') }}</flux:heading>
                <flux:text>{{ $recipe->notes }}</flux:text>
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
