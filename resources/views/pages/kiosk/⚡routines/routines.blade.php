<div class="flex h-full flex-col overflow-hidden" wire:poll.600s>
    <div class="flex shrink-0 flex-col gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-baseline gap-3">
            <x-ui.clock :timezone="auth()->user()->currentTeam->timezone" />
            <flux:heading size="lg">{{ $this->heading }}</flux:heading>
        </div>

        <flux:button.group>
            <flux:button icon="chevron-left" wire:click="previous" />
            <flux:button wire:click="current" :disabled="$this->isToday">{{ __('Today') }}</flux:button>
            <flux:button icon="chevron-right" wire:click="next" />
        </flux:button.group>
    </div>

    @if (count($this->columns) === 0)
        <div class="flex flex-1 flex-col items-center justify-center gap-3 p-6 text-center">
            <flux:icon name="party-popper" class="size-12 text-zinc-400" />
            <flux:heading size="lg">{{ __('Nothing to do') }}</flux:heading>
            <flux:text>
                {{ $this->isToday
                    ? __('No routines are scheduled today.')
                    : __('No routines were scheduled on this day.') }}
            </flux:text>
        </div>
    @else
        <div class="min-h-0 flex-1 overflow-x-auto p-4">
            <div class="flex h-full min-w-full gap-4">
                @foreach ($this->columns as $column)
                    <section
                        wire:key="routine-column-{{ $column['key'] }}"
                        class="flex h-full w-80 shrink-0 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <header class="shrink-0 border-b border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex items-baseline justify-between gap-2">
                                <flux:heading size="lg">{{ $column['name'] }}</flux:heading>
                                <flux:text class="tabular-nums">
                                    {{ $column['completed'] }}/{{ $column['total'] }}
                                </flux:text>
                            </div>

                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                <div
                                    class="h-full rounded-full bg-(--color-accent) transition-[width] duration-300"
                                    style="width: {{ $column['total'] > 0 ? round($column['completed'] / $column['total'] * 100) : 0 }}%"
                                ></div>
                            </div>
                        </header>

                        <div class="min-h-0 flex-1 space-y-5 overflow-y-auto p-4">
                            @foreach ($column['occurrences'] as $occurrence)
                                <div wire:key="routine-occurrence-{{ $occurrence->id }}">
                                    <div class="mb-2 flex items-center gap-2">
                                        <flux:icon :name="$occurrence->routine->time_of_day->getIcon()" class="size-4 text-zinc-400" />
                                        <flux:heading class="uppercase tracking-wide">{{ $occurrence->routine->name }}</flux:heading>
                                    </div>

                                    <ul class="space-y-2">
                                        @foreach ($occurrence->steps as $step)
                                            <li wire:key="routine-occurrence-step-{{ $step->id }}">
                                                <button
                                                    type="button"
                                                    wire:click="toggle({{ $step->id }})"
                                                    @class([
                                                        'flex w-full items-center gap-3 rounded-lg border p-3 text-left transition',
                                                        'border-zinc-200 bg-white hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-800 dark:hover:border-zinc-600' => ! $step->isCompleted(),
                                                        'border-transparent bg-zinc-100 dark:bg-zinc-800/50' => $step->isCompleted(),
                                                    ])
                                                >
                                                    @if ($step->isCompleted())
                                                        <flux:icon name="check-circle" variant="solid" class="size-6 shrink-0 text-(--color-accent)" />
                                                    @else
                                                        <span class="size-6 shrink-0 rounded-full border-2 border-zinc-300 dark:border-zinc-600"></span>
                                                    @endif

                                                    <span @class([
                                                        'flex-1',
                                                        'text-zinc-400 line-through dark:text-zinc-500' => $step->isCompleted(),
                                                        'text-zinc-800 dark:text-zinc-100' => ! $step->isCompleted(),
                                                    ])>
                                                        {{ $step->step?->name }}
                                                    </span>

                                                    @if ($step->isCompleted() && $step->completedBy)
                                                        <flux:text size="sm" variant="subtle" class="shrink-0">
                                                            {{ $step->completedBy->name }}
                                                        </flux:text>
                                                    @endif
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @endif
</div>
