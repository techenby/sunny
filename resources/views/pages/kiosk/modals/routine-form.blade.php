@teleport('body')
<flux:modal name="routine-form" flyout variant="floating" scroll="body" class="md:w-lg">
    <form wire:submit="save" class="space-y-8">
        <div class="space-y-1">
            <flux:heading size="lg">{{ $form->editingRoutine ? __('Edit routine') : __('Add routine') }}</flux:heading>
            <flux:text variant="subtle">{{ __('Set the schedule and build the checklist shown on your kiosk.') }}</flux:text>
        </div>

        <flux:input
            wire:model="form.name"
            name="routine-name"
            :label="__('Routine name')"
            type="text"
            :placeholder="__('Morning routine')"
            required
        />

        <flux:radio.group wire:model.live="form.cadence" :label="__('Repeats')" variant="cards" class="max-sm:flex-col">
            <flux:radio
                :value="\App\Enums\RoutineCadence::Daily->value"
                :label="__('Daily')"
                :description="__('Starts fresh every day.')"
            />
            <flux:radio
                :value="\App\Enums\RoutineCadence::Weekly->value"
                :label="__('Weekly')"
                :description="__('Starts fresh once a week.')"
            />
        </flux:radio.group>

        @if ($form->cadence === \App\Enums\RoutineCadence::Weekly->value)
            <flux:select
                wire:model="form.reset_weekday"
                name="routine-reset-day"
                :label="__('Reset day')"
                :description="__('The checklist starts fresh on this day.')"
                variant="listbox"
                wire:key="routine-reset-day"
            >
                @foreach ([__('Sunday'), __('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday')] as $dayIndex => $day)
                    <flux:select.option :value="$dayIndex">{{ $day }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:separator variant="subtle" />

        <div class="space-y-4">
            <div class="space-y-1">
                <flux:heading>{{ __('Tasks') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Add the steps in the order they should be completed.') }}</flux:text>
            </div>

            <div class="space-y-3 rounded-xl bg-zinc-50 p-3 dark:bg-white/5">
                <div class="space-y-2">
                    @foreach ($form->tasks as $index => $task)
                        <div class="flex items-start gap-2" wire:key="{{ $task['key'] }}">
                            <div class="flex h-9 w-6 shrink-0 items-center justify-center tabular-nums text-zinc-500 dark:text-zinc-400">
                                {{ $index + 1 }}
                            </div>

                            <flux:field class="min-w-0 flex-1">
                                <flux:label class="sr-only">{{ __('Task :number', ['number' => $index + 1]) }}</flux:label>
                                <flux:input
                                    wire:model="form.tasks.{{ $index }}.name"
                                    name="routine-task-{{ $index }}"
                                    type="text"
                                    :placeholder="__('Task :number', ['number' => $index + 1])"
                                    required
                                />
                                <flux:error :name="'form.tasks.' . $index . '.name'" />
                            </flux:field>

                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                icon="x-mark"
                                :tooltip="__('Remove task')"
                                wire:click="removeTask({{ $index }})"
                                :disabled="count($form->tasks) === 1"
                            />
                        </div>
                    @endforeach
                </div>

                <flux:button type="button" variant="ghost" size="sm" icon="plus" wire:click="addTask">
                    {{ __('Add task') }}
                </flux:button>
            </div>
        </div>

        <flux:separator variant="subtle" />

        <div class="flex items-center justify-end gap-2">
            <flux:modal.close>
                <flux:button type="button" variant="filled">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>

            <flux:button type="submit" variant="primary">
                {{ $form->editingRoutine ? __('Save changes') : __('Create routine') }}
            </flux:button>
        </div>
    </form>
</flux:modal>
@endteleport
