@teleport('body')
<flux:modal name="routine-form" flyout variant="floating" class="md:w-96">
    <form wire:submit="save" class="space-y-6">
        <flux:heading size="lg">{{ $form->editingRoutine ? __('Edit Routine') : __('Add Routine') }}</flux:heading>

        <flux:input wire:model="form.name" :label="__('Name')" :placeholder="__('Morning Routine')" type="text" required />

        <flux:select wire:model="form.user_id" :label="__('Assigned to')" variant="listbox">
            <flux:select.option value="">{{ __('Household') }}</flux:select.option>
            @foreach ($this->members as $member)
                <flux:select.option :value="$member->id">{{ $member->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model="form.time_of_day" :label="__('Time of day')" variant="listbox">
            @foreach (\App\Enums\TimeOfDay::cases() as $timeOfDay)
                <flux:select.option :value="$timeOfDay->value">{{ $timeOfDay->getLabel() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model.live="form.frequency" :label="__('Repeats')" variant="listbox">
            @foreach (\App\Enums\RoutineFrequency::cases() as $frequency)
                <flux:select.option :value="$frequency->value">{{ $frequency->getLabel() }}</flux:select.option>
            @endforeach
        </flux:select>

        @if ($form->frequency === \App\Enums\RoutineFrequency::Weekly->value)
            <flux:checkbox.group wire:model="form.weekdays" :label="__('On these days')">
                @foreach (\Carbon\CarbonImmutable::getDays() as $number => $day)
                    <flux:checkbox :value="$number" :label="$day" />
                @endforeach
            </flux:checkbox.group>
        @endif

        @if ($form->frequency === \App\Enums\RoutineFrequency::Monthly->value)
            <flux:input
                wire:model="form.day_of_month"
                :label="__('Day of the month')"
                :description="__('Routines set past the end of a short month run on its last day.')"
                type="number"
                min="1"
                max="31"
            />
        @endif

        <flux:input wire:model="form.starts_on" :label="__('Starts on')" type="date" required />

        <flux:switch wire:model="form.is_active" :label="__('Active')" :description="__('Paused routines stop appearing on the kiosk.')" />

        <div class="flex">
            <flux:spacer />
            <flux:modal.close>
                <flux:button variant="ghost" class="mr-2">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button type="submit" variant="primary">{{ $form->editingRoutine ? __('Update') : __('Create') }}</flux:button>
        </div>
    </form>
</flux:modal>
@endteleport
