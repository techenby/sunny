<div>
    <form wire:submit="save" class="space-y-6 p-6 lg:p-8">
        <div class="grid gap-6 sm:grid-cols-2">
            <flux:select wire:model="form.timezone" :label="__('Timezone')" variant="listbox" searchable>
                @foreach (DateTimeZone::listIdentifiers() as $timezoneOption)
                    <flux:select.option value="{{ $timezoneOption }}">{{ str_replace('_', ' ', $timezoneOption) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="form.week_start" :label="__('Calendar Week Start')" variant="listbox" searchable>
                <flux:select.option value="0">{{ __('Sunday') }}</flux:select.option>
                <flux:select.option value="1">{{ __('Monday') }}</flux:select.option>
                <flux:select.option value="2">{{ __('Tuesday') }}</flux:select.option>
                <flux:select.option value="3">{{ __('Wednesday') }}</flux:select.option>
                <flux:select.option value="4">{{ __('Thursday') }}</flux:select.option>
                <flux:select.option value="5">{{ __('Friday') }}</flux:select.option>
                <flux:select.option value="6">{{ __('Saturday') }}</flux:select.option>
            </flux:select>

            <flux:radio.group wire:model="form.appearance" :label="__('Appearance')" variant="segmented">
                <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
            </flux:radio.group>

            <flux:radio.group wire:model="form.layout" :label="__('Layout')" variant="segmented">
                <flux:radio value="portrait">{{ __('Portrait') }}</flux:radio>
                <flux:radio value="landscape">{{ __('Landscape') }}</flux:radio>
            </flux:radio.group>
        </div>

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

    <section class="space-y-4 border-t border-zinc-200 p-6 dark:border-zinc-700 lg:p-8">
        <flux:heading size="lg">{{ __('Paired displays') }}</flux:heading>
        <flux:text class="text-zinc-500 dark:text-zinc-400">
            {{ __('Devices that are signed in to the kiosk view for this team.') }}
        </flux:text>

        @if ($this->pairedDevices->isEmpty())
            <flux:text class="text-sm italic text-zinc-500 dark:text-zinc-400">
                {{ __('No paired displays yet.') }}
            </flux:text>
        @else
            <ul class="space-y-2">
                @foreach ($this->pairedDevices as $device)
                    <flux:card class="flex items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <flux:heading>{{ $device->name ?: __('Unnamed display') }}</flux:heading>
                            <flux:text variant="subtle" class="text-xs">
                                {{ __('Paired') }} {{ $device->paired_at?->diffForHumans() }}
                                @if ($device->last_seen_at)
                                    · {{ __('Last seen') }} {{ $device->last_seen_at->diffForHumans() }}
                                @endif
                            </flux:text>
                        </div>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            wire:click="forget({{ $device->id }})"
                            wire:confirm="{{ __('Forget this display? It will return to the QR pairing screen.') }}"
                        >
                            {{ __('Forget') }}
                        </flux:button>
                    </flux:card>
                @endforeach
            </ul>
        @endif
    </section>
</div>

@assets
<script id="search-js" defer="" src="https://api.mapbox.com/search-js/v1.5.0/web.js"></script>
@endassets

@script
<script>
    const autofill = document.querySelector('mapbox-address-autofill');

    // Mapbox returns GeoJSON [lng, lat] but retrieve event emits [lng, lat] in this order
    autofill.addEventListener('retrieve', (event) => {
        $wire.$set('form.address.long', event.detail.features[0].geometry.coordinates[0]);
        $wire.$set('form.address.lat', event.detail.features[0].geometry.coordinates[1]);
    });
</script>
@endscript
