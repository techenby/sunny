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
                                <div class="flex min-w-0 items-baseline gap-2">
                                    <flux:icon :name="$column['icon']" class="size-4 shrink-0 translate-y-0.5 text-zinc-400" />
                                    <flux:heading size="lg" class="truncate">{{ $column['name'] }}</flux:heading>
                                </div>

                                <flux:text class="tabular-nums">
                                    {{ $column['completed'] }}/{{ $column['total'] }}
                                </flux:text>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <flux:badge size="sm" :color="$column['isHousehold'] ? 'zinc' : 'blue'">
                                    {{ $column['assignee'] }}
                                </flux:badge>

                                <flux:badge size="sm" color="zinc">{{ $column['timeOfDay'] }}</flux:badge>
                            </div>

                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                                <div
                                    class="h-full rounded-full bg-(--color-accent) transition-[width] duration-300"
                                    style="width: {{ $column['total'] > 0 ? round($column['completed'] / $column['total'] * 100) : 0 }}%"
                                ></div>
                            </div>
                        </header>

                        <div class="min-h-0 flex-1 overflow-y-auto p-4">
                            @if ($column['total'] === 0)
                                <flux:text variant="subtle">{{ __('No steps yet.') }}</flux:text>
                            @else
                                <ul class="space-y-2">
                                    @foreach ($column['steps'] as $step)
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
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @endif
</div>
