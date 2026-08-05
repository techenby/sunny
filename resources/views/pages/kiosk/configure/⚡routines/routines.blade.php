<div class="space-y-6">
    <div class="flex items-start justify-between gap-4 max-sm:flex-col">
        <div class="space-y-1">
            <flux:heading size="lg">{{ __('Routines') }}</flux:heading>
            <flux:text variant="subtle">{{ __('Create the recurring checklists shown on your kiosk.') }}</flux:text>
        </div>

        <flux:button type="button" variant="primary" size="sm" icon="plus" wire:click="add" class="max-sm:w-full">
            {{ __('Add routine') }}
        </flux:button>
    </div>

    <div class="space-y-3">
        @forelse ($this->routines as $routine)
            <flux:card size="sm" class="space-y-4" wire:key="routine-{{ $routine->id }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 space-y-2">
                        <flux:heading class="truncate font-medium">{{ $routine->name }}</flux:heading>

                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                            <flux:badge
                                size="sm"
                                :icon="$routine->cadence === \App\Enums\RoutineCadence::Weekly ? 'calendar-days' : 'sun'"
                                icon:variant="micro"
                            >
                                {{ $routine->cadence->label() }}
                            </flux:badge>

                            <flux:text variant="subtle">
                                {{ trans_choice(':count task|:count tasks', $routine->tasks->count(), ['count' => $routine->tasks->count()]) }}
                            </flux:text>

                            @if ($routine->cadence === \App\Enums\RoutineCadence::Weekly)
                                <flux:text variant="subtle">
                                    ·
                                    {{ __('Resets :day', ['day' => [__('Sunday'), __('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday')][$routine->reset_weekday] ?? __('Unknown day')]) }}
                                </flux:text>
                            @endif
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <flux:button
                            type="button"
                            variant="ghost"
                            size="sm"
                            icon="pencil"
                            :tooltip="__('Edit routine')"
                            wire:click="edit({{ $routine->id }})"
                        />

                        <flux:button
                            type="button"
                            variant="ghost"
                            size="sm"
                            icon="trash"
                            :tooltip="__('Remove routine')"
                            wire:click="delete({{ $routine->id }})"
                            wire:confirm="{{ __('Remove this routine and all of its tasks?') }}"
                        />
                    </div>
                </div>

                @if ($routine->tasks->isNotEmpty())
                    <div class="rounded-lg bg-zinc-50 p-3 dark:bg-white/5">
                        <ul role="list" class="space-y-2">
                            @foreach ($routine->tasks->take(3) as $task)
                                <li class="flex items-start gap-2">
                                    <flux:icon name="check-circle" variant="micro" class="size-4 h-lh shrink-0 stroke-zinc-400 dark:stroke-zinc-500" />
                                    <flux:text class="min-w-0 truncate">{{ $task->name }}</flux:text>
                                </li>
                            @endforeach
                        </ul>

                        @if ($routine->tasks->count() > 3)
                            <flux:text variant="subtle" class="pt-2">
                                {{ trans_choice('+:count more task|+:count more tasks', $routine->tasks->count() - 3, ['count' => $routine->tasks->count() - 3]) }}
                            </flux:text>
                        @endif
                    </div>
                @endif
            </flux:card>
        @empty
            <div class="space-y-4 rounded-xl border border-dashed border-zinc-950/10 p-6 dark:border-white/10">
                <div class="space-y-1">
                    <flux:heading>{{ __('No routines yet') }}</flux:heading>
                    <flux:text variant="subtle">{{ __('Add a routine to create your first recurring checklist.') }}</flux:text>
                </div>

                <flux:button type="button" variant="filled" size="sm" icon="plus" wire:click="add">
                    {{ __('Add your first routine') }}
                </flux:button>
            </div>
        @endforelse
    </div>

    @include('pages.kiosk.modals.routine-form')
</div>
