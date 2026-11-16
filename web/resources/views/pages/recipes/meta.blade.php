@php
    $editable ??= false;
    $times = array_filter([
        __('Prep') => $recipe->prep_time,
        __('Cook') => $recipe->cook_time,
        __('Total') => $recipe->total_time,
    ]);
@endphp

@if ($recipe->source || $recipe->servings || $times || $editable)
    <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400 [&>*+*]:border-l [&>*+*]:border-zinc-200 [&>*+*]:pl-4 dark:[&>*+*]:border-white/10">
        @if ($recipe->source)
            <div>
                @if ($recipe->isSourceUrl())
                    <flux:link href="{{ $recipe->source }}" target="_blank" variant="ghost" class="inline-flex items-center gap-1 text-sm text-(--color-accent-content)!">
                        {{ $recipe->shortenedSource() }}
                        <flux:icon.arrow-up-right variant="micro" />
                    </flux:link>
                @else
                    {{ $recipe->source }}
                @endif
            </div>
        @endif

        @if ($recipe->servings || $editable)
            <div class="flex items-center gap-1.5">
                <flux:icon.users variant="micro" />
                @if ($recipe->servings)
                    <span>
                        <span class="text-zinc-800 dark:text-white">{{ $recipe->servings }}</span>
                        @if (is_numeric($recipe->servings))
                            {{ trans_choice('serving|servings', (int) $recipe->servings) }}
                        @endif
                    </span>
                @else
                    {{ __('No servings yet') }}
                @endif
            </div>
        @endif

        @if ($times || $editable)
            <div class="flex items-center gap-1.5">
                <flux:icon.clock variant="micro" />
                @forelse ($times as $label => $time)
                    <span>{{ $label }} <span class="text-zinc-800 dark:text-white">{{ $time }}</span></span>
                    @unless ($loop->last)
                        <span aria-hidden="true">&middot;</span>
                    @endunless
                @empty
                    {{ __('No times yet') }}
                @endforelse
            </div>
        @endif
    </div>
@endif
