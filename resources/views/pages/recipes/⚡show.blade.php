<?php

use App\Models\Recipe;
use Livewire\Component;

new class extends Component {
    public Recipe $recipe;

    public function remix(): void
    {
        $this->authorize('remix', $this->recipe);

        $recipe = $this->recipe->createRemix();

        $this->redirect(route('recipes.show', $recipe), navigate: true);
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <flux:button :href="route('recipes.index')" icon="arrow-left" variant="ghost" wire:navigate />
            <flux:heading size="xl">{{ $recipe->name }}</flux:heading>
        </div>
        <div class="flex gap-2">
            <flux:button wire:click="remix" icon="document-duplicate">{{ __('Remix') }}</flux:button>
            <flux:button :href="route('recipes.edit', $recipe)" icon="pencil" wire:navigate>{{ __('Edit') }}</flux:button>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($recipe->description)
                <flux:card>
                    <flux:heading size="lg" class="mb-2">{{ __('Description') }}</flux:heading>
                    <flux:text>{{ $recipe->description }}</flux:text>
                </flux:card>
            @endif

            @if ($recipe->ingredients)
                <flux:card>
                    <flux:heading size="lg" class="mb-2">{{ __('Ingredients') }}</flux:heading>
                    <div class="prose dark:prose-invert">
                        {!! nl2br(e($recipe->ingredients)) !!}
                    </div>
                </flux:card>
            @endif

            @if ($recipe->instructions)
                <flux:card>
                    <flux:heading size="lg" class="mb-2">{{ __('Instructions') }}</flux:heading>
                    <div class="prose dark:prose-invert">
                        {!! nl2br(e($recipe->instructions)) !!}
                    </div>
                </flux:card>
            @endif

            @if ($recipe->notes)
                <flux:card>
                    <flux:heading size="lg" class="mb-2">{{ __('Notes') }}</flux:heading>
                    <flux:text>{{ $recipe->notes }}</flux:text>
                </flux:card>
            @endif
        </div>

        <div class="space-y-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Details') }}</flux:heading>

                <dl class="space-y-4">
                    @if ($recipe->source)
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Source') }}</dt>
                            <dd class="mt-1">
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
                            <dd class="mt-1">{{ $recipe->servings }}</dd>
                        </div>
                    @endif

                    @if ($recipe->prep_time)
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Prep Time') }}</dt>
                            <dd class="mt-1">{{ $recipe->prep_time }}</dd>
                        </div>
                    @endif

                    @if ($recipe->cook_time)
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Cook Time') }}</dt>
                            <dd class="mt-1">{{ $recipe->cook_time }}</dd>
                        </div>
                    @endif

                    @if ($recipe->total_time)
                        <div>
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Total Time') }}</dt>
                            <dd class="mt-1">{{ $recipe->total_time }}</dd>
                        </div>
                    @endif
                </dl>
            </flux:card>

            @if ($recipe->nutrition)
                <flux:card>
                    <flux:heading size="lg" class="mb-2">{{ __('Nutrition') }}</flux:heading>
                    <div class="prose dark:prose-invert">
                        {!! nl2br(e($recipe->nutrition)) !!}
                    </div>
                </flux:card>
            @endif

            @if ($recipe->parent)
                <flux:card>
                    <flux:heading size="lg" class="mb-2">{{ __('Remixed From') }}</flux:heading>
                    <flux:link href="{{ route('recipes.show', $recipe->parent) }}" wire:navigate>
                        {{ $recipe->parent->name }}
                    </flux:link>
                </flux:card>
            @endif

            @if ($recipe->remixes->isNotEmpty())
                <flux:card>
                    <flux:heading size="lg" class="mb-2">{{ __('Remixes') }}</flux:heading>
                    <ul class="list-disc ml-4 space-y-1">
                        @foreach ($recipe->remixes as $remix)
                        <li>
                            <flux:link href="{{ route('recipes.show', $remix) }}" wire:navigate>
                                {{ $remix->name }}
                            </flux:link>
                        </li>
                        @endforeach
                    </ul>
                </flux:card>
            @endif
        </div>
    </div>
</div>
