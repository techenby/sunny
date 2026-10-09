<div class="flex h-full flex-col overflow-hidden" wire:poll.600s>
    <div class="flex shrink-0 flex-col gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-baseline gap-3">
            <x-ui.clock :timezone="$this->team->timezone" />
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
                        wire:key="routine-column-{{ $column['key'] }}-{{ $column['state'] }}"
                        x-data="{
                            completed: @js($column['completed']),
                            completedBy: @js($column['steps']->mapWithKeys(fn ($step) => [$step->id => $step->isCompleted() ? $step->completedBy?->name : null])),
                            async setCompleted(checkbox, stepId) {
                                let completed = checkbox.checked
                                let previousCompletedBy = this.completedBy[stepId]

                                this.completed += completed ? 1 : -1
                                this.completedBy[stepId] = completed ? @js(auth()->user()->name) : null

                                try {
                                    await $wire.setStepCompleted(stepId, completed)
                                } catch {
                                    checkbox.checked = ! completed
                                    this.completed += completed ? -1 : 1
                                    this.completedBy[stepId] = previousCompletedBy
                                }
                            },
                        }"
                        class="flex h-full w-80 shrink-0 flex-col overflow-hidden rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900"
                    >
                        <header class="shrink-0 border-b border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex items-baseline justify-between gap-2">
                                <div class="flex min-w-0 items-baseline gap-2">
                                    <flux:icon :name="$column['icon']" class="size-4 shrink-0 translate-y-0.5 text-zinc-400" />
                                    <flux:heading size="lg" class="truncate">{{ $column['name'] }}</flux:heading>
                                </div>

                                <flux:text class="tabular-nums">
                                    <span x-text="completed">{{ $column['completed'] }}</span>/{{ $column['total'] }}
                                </flux:text>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <flux:badge size="sm" :color="$column['isHousehold'] ? 'zinc' : 'blue'">
                                    {{ $column['assignee'] }}
                                </flux:badge>

                                <flux:badge size="sm" color="zinc">{{ $column['timeOfDay'] }}</flux:badge>
                            </div>

                            <flux:progress
                                :value="$column['completed']"
                                :max="$column['total']"
                                x-effect="$el.value = completed"
                                class="mt-2 h-2"
                            />
                        </header>

                        <div class="min-h-0 flex-1 overflow-y-auto p-4">
                            @if ($column['total'] === 0)
                                <flux:text variant="subtle">{{ __('No steps yet.') }}</flux:text>
                            @else
                                <flux:checkbox.group
                                    variant="cards"
                                    class="flex-col"
                                    :aria-label="__('Routine steps')"
                                >
                                    @foreach ($column['steps'] as $step)
                                        <flux:checkbox
                                            wire:key="routine-occurrence-step-{{ $step->id }}"
                                            :value="$step->id"
                                            :checked="$step->isCompleted()"
                                            x-on:change="setCompleted($el, {{ $step->id }})"
                                            class="items-center"
                                        >
                                            <flux:checkbox.indicator />

                                            <span class="flex-1 text-zinc-800 dark:text-zinc-100 [ui-checkbox[data-checked]_&]:text-zinc-400 [ui-checkbox[data-checked]_&]:line-through dark:[ui-checkbox[data-checked]_&]:text-zinc-500">
                                                {{ $step->step?->name }}
                                            </span>

                                            <flux:text
                                                size="sm"
                                                variant="subtle"
                                                class="shrink-0"
                                                x-show="completedBy[{{ $step->id }}]"
                                                x-text="completedBy[{{ $step->id }}]"
                                            >{{ $step->isCompleted() ? $step->completedBy?->name : '' }}</flux:text>
                                        </flux:checkbox>
                                    @endforeach
                                </flux:checkbox.group>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        </div>
    @endif
</div>
