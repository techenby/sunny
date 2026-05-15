<form wire:submit="save" class="space-y-6 p-6 lg:p-8'">
    <flux:select wire:model="form.timezone" :label="__('Timezone')" variant="listbox">
        @foreach (DateTimeZone::listIdentifiers() as $timezoneOption)
            <flux:select.option value="{{ $timezoneOption }}">{{ str_replace('_', ' ', $timezoneOption) }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:select wire:model="form.week_start" :label="__('Calendar Week Start')" variant="listbox">
            <flux:select.option value="0">{{ __('Sunday') }}</flux:select.option>
            <flux:select.option value="1">{{ __('Monday') }}</flux:select.option>
            <flux:select.option value="2">{{ __('Tuesday') }}</flux:select.option>
            <flux:select.option value="3">{{ __('Wednesday') }}</flux:select.option>
            <flux:select.option value="4">{{ __('Thursday') }}</flux:select.option>
            <flux:select.option value="5">{{ __('Friday') }}</flux:select.option>
            <flux:select.option value="6">{{ __('Saturday') }}</flux:select.option>
    </flux:select>

    <flux:button type="submit" variant="primary">Save</flux:button>
</form>
