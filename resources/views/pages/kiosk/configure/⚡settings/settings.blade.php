<form wire:submit="save" class="space-y-6 p-6 lg:p-8">
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

    <flux:field>
        <flux:label>Address for Weather</flux:label>

        <mapbox-address-autofill access-token="{{ config('services.mapbox.key') }}">
            <flux:input wire:model="form.address.address" name="address" autocomplete="address-line1" placeholder="Address"/>
        </mapbox-address-autofill>

        <div class="flex gap-2 mt-2">
            <flux:input wire:model="form.address.city" name="city" autocomplete="address-level2" placeholder="City" />
            <flux:input wire:model="form.address.state" name="state" autocomplete="address-level1" placeholder="State" />
            <flux:input wire:model="form.address.zip" name="postcode" autocomplete="postal-code" placeholder="ZIP / Postcode" />
        </div>
    </flux:field>

    <flux:button type="submit" variant="primary">Save</flux:button>
</form>

@assets
<script id="search-js" defer="" src="https://api.mapbox.com/search-js/v1.5.0/web.js"></script>
@endassets

@script
<script>
    const autofill = document.querySelector('mapbox-address-autofill');

    autofill.addEventListener('retrieve', (event) => {
        $wire.$set('form.address.lat', event.detail.features[0].geometry.coordinates[0]);
        $wire.$set('form.address.long', event.detail.features[0].geometry.coordinates[1]);
    });
</script>
@endscript
