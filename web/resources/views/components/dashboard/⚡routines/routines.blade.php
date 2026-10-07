<flux:card body="flush" class="flex flex-col">
    <flux:card.header>
        <flux:card.heading size="lg">{{ __('Today\'s routines') }}</flux:card.heading>
        <flux:card.actions>
            <flux:button size="sm" variant="ghost" icon:trailing="arrow-right" :href="route('routines.index')" wire:navigate>
                {{ __('All routines') }}
            </flux:button>
        </flux:card.actions>
    </flux:card.header>

    <flux:card.body class="flex-1">
        @if ($this->occurrences->isEmpty())
            <div class="py-6 text-center">
                <flux:icon name="party-popper" class="mx-auto mb-3 size-10 text-zinc-400" />
                <flux:text>{{ __('Nothing scheduled for you today.') }}</flux:text>
            </div>
        @else
            <div class="space-y-5">
                @foreach ($this->occurrences as $occurrence)
                    @php
                        $completed = $occurrence->steps->filter(fn ($step) => $step->isCompleted())->count();
                        $total = $occurrence->steps->count();
                    @endphp

                    <section wire:key="dashboard-occurrence-{{ $occurrence->id }}" class="space-y-2">
                        <div class="flex items-baseline justify-between gap-2">
                            <div class="flex min-w-0 items-baseline gap-2">
                                <flux:icon :name="$occurrence->routine->time_of_day->getIcon()" class="size-4 shrink-0 translate-y-0.5 text-zinc-400" />
                                <flux:heading class="truncate">{{ $occurrence->routine->name }}</flux:heading>
                                @if ($occurrence->routine->user === null)
                                    <flux:badge size="sm" color="zinc">{{ __('Household') }}</flux:badge>
                                @endif
                            </div>

                            <flux:text size="sm" class="tabular-nums">{{ $completed }}/{{ $total }}</flux:text>
                        </div>

                        <flux:progress :value="$completed" :max="max($total, 1)" class="h-1.5" />

                        @if ($total > 0)
                            <ul>
                                @foreach ($occurrence->steps as $step)
                                    <li wire:key="dashboard-occurrence-step-{{ $step->id }}">
                                        <button
                                            type="button"
                                            wire:click="toggle({{ $step->id }})"
                                            role="checkbox"
                                            aria-checked="{{ $step->isCompleted() ? 'true' : 'false' }}"
                                            class="-mx-2 flex w-full items-center gap-3 rounded-md px-2 py-1.5 text-left hover:bg-zinc-50 dark:hover:bg-zinc-800/50"
                                        >
                                            @if ($step->isCompleted())
                                                <flux:icon name="check-circle" variant="solid" class="size-5 shrink-0 text-(--color-accent)" />
                                            @else
                                                <span class="size-5 shrink-0 rounded-full border-2 border-zinc-300 dark:border-zinc-600"></span>
                                            @endif

                                            <span @class([
                                                'flex-1 text-sm',
                                                'text-zinc-400 line-through dark:text-zinc-500' => $step->isCompleted(),
                                                'text-zinc-800 dark:text-zinc-100' => ! $step->isCompleted(),
                                            ])>
                                                {{ $step->step?->name }}
                                            </span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </flux:card.body>
</flux:card>
