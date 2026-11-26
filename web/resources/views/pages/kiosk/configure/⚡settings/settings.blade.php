<div>
    <form wire:submit="save" class="space-y-6 p-6 lg:p-8">
        @unless ($this->canUpdateTeam)
            <flux:callout variant="secondary" icon="lock-closed" data-test="team-settings-read-only">
                <flux:callout.text>{{ __('Only the team owner or an admin can change the timezone, week start, appearance, calendar, rotation, address, and paired displays.') }}</flux:callout.text>
            </flux:callout>
        @endunless

        <flux:select wire:model="form.timezone" :label="__('Timezone')" variant="listbox" :disabled="! $this->canUpdateTeam">
            @foreach (DateTimeZone::listIdentifiers() as $timezoneOption)
                <flux:select.option value="{{ $timezoneOption }}">{{ str_replace('_', ' ', $timezoneOption) }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select wire:model="form.week_start" :label="__('Week starts on')" variant="listbox" :disabled="! $this->canUpdateTeam">
            <flux:select.option value="0">{{ __('Sunday') }}</flux:select.option>
            <flux:select.option value="1">{{ __('Monday') }}</flux:select.option>
            <flux:select.option value="2">{{ __('Tuesday') }}</flux:select.option>
            <flux:select.option value="3">{{ __('Wednesday') }}</flux:select.option>
            <flux:select.option value="4">{{ __('Thursday') }}</flux:select.option>
            <flux:select.option value="5">{{ __('Friday') }}</flux:select.option>
            <flux:select.option value="6">{{ __('Saturday') }}</flux:select.option>
        </flux:select>

        <flux:radio.group wire:model="form.appearance" :label="__('Appearance')" variant="segmented" :disabled="! $this->canUpdateTeam">
            <flux:radio value="light" icon="sun" :label="__('Light')" />
            <flux:radio value="dark" icon="moon" :label="__('Dark')" />
            <flux:radio value="system" icon="computer-desktop" :label="__('System')" />
        </flux:radio.group>

        <flux:select wire:model="form.calendar_view" :label="__('Calendar opens on')" :description="__('Automatic shows the month on large displays and the week on smaller ones.')" variant="listbox" :disabled="! $this->canUpdateTeam">
            @foreach (App\Enums\CalendarView::cases() as $view)
                <flux:select.option :value="$view->value">{{ $view->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:radio.group wire:model="form.calendar_event_style" :label="__('Event style')" variant="cards" class="max-sm:flex-col" :disabled="! $this->canUpdateTeam">
            @foreach (App\Enums\CalendarEventStyle::cases() as $eventStyle)
                <flux:radio :value="$eventStyle->value" class="flex-1" data-test="event-style-{{ $eventStyle->value }}">
                    <div class="flex flex-1 flex-col gap-3">
                        <div class="flex items-center gap-2">
                            <flux:radio.indicator />
                            <flux:heading>{{ $eventStyle->label() }}</flux:heading>
                        </div>

                        @foreach ([[App\Enums\CalendarColor::Blue, '9:00 AM', __('Soccer practice')], [App\Enums\CalendarColor::Orange, '5:30 PM', __('Dinner at Grandma’s')]] as [$color, $time, $title])
                            <x-kiosk.calendar.event-surface :event="['feed_color' => $color]" :$eventStyle class="rounded-md p-2 text-sm">
                                <div class="mb-1 text-xs font-medium text-(--event-muted)">{{ $time }}</div>
                                <div class="font-medium leading-snug">{{ $title }}</div>
                            </x-kiosk.calendar.event-surface>
                        @endforeach
                    </div>
                </flux:radio>
            @endforeach
        </flux:radio.group>

        <flux:radio.group wire:model="form.rotation" :label="__('Rotation')" :description="__('Rotates the kiosk display for devices that ignore the browser orientation setting.')" variant="segmented" :disabled="! $this->canUpdateTeam">
            <flux:radio value="0" label="0°" />
            <flux:radio value="90" label="90°" />
            <flux:radio value="180" label="180°" />
            <flux:radio value="270" label="270°" />
        </flux:radio.group>

        <div class="grid gap-6 sm:grid-cols-2">
            <flux:select wire:model="form.screensaver_after" :label="__('Screensaver')" :description="__('Shows a clock when the display is idle.')" variant="listbox">
                <flux:select.option value="0">{{ __('Never') }}</flux:select.option>
                @foreach ([1, 2, 5, 10, 15, 30] as $minutes)
                    <flux:select.option value="{{ $minutes }}">{{ trans_choice('After :count minute|After :count minutes', $minutes) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="form.return_home_after" :label="__('Return to calendar')" :description="__('Shows today when the display is idle.')" variant="listbox">
                <flux:select.option value="0">{{ __('Never') }}</flux:select.option>
                @foreach ([5, 10, 15, 30, 60] as $minutes)
                    <flux:select.option value="{{ $minutes }}">{{ trans_choice('After :count minute|After :count minutes', $minutes) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:text variant="subtle" class="-mt-3 text-sm">
            {{ __('Both wait 30 minutes instead while the routines board shows a routine in progress.') }}
        </flux:text>

        <flux:fieldset>
            <flux:switch wire:model.live="form.night_mode" :label="__('Night mode')" :description="__('A dim red clock replaces the screensaver overnight.')" align="left" />

            <div class="mt-4 flex gap-4" wire:show="form.night_mode">
                <flux:input type="time" wire:model="form.night_starts_at" :label="__('Starts')" />
                <flux:input type="time" wire:model="form.night_ends_at" :label="__('Ends')" />
            </div>
        </flux:fieldset>

        <div class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Address for weather') }}</flux:label>

                <mapbox-address-autofill access-token="{{ config('services.mapbox.key') }}">
                    <flux:input wire:model="form.address.address" name="address" autocomplete="address-line1" :disabled="! $this->canUpdateTeam" />
                </mapbox-address-autofill>

                <flux:error name="form.address.address" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="form.address.city" :label="__('City')" name="city" autocomplete="address-level2" :disabled="! $this->canUpdateTeam" />
                <flux:input wire:model="form.address.state" :label="__('State')" name="state" autocomplete="address-level1" :disabled="! $this->canUpdateTeam" />
                <flux:input wire:model="form.address.zip" :label="__('ZIP')" name="postcode" autocomplete="postal-code" :disabled="! $this->canUpdateTeam" />
            </div>
        </div>

        <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
    </form>

    <section class="space-y-4 border-t border-zinc-200 p-6 dark:border-zinc-700 lg:p-8">
        <flux:heading size="lg">{{ __('Paired displays') }}</flux:heading>
        <flux:text class="text-zinc-500 dark:text-zinc-400">
            {{ __('Devices that are signed in to the kiosk view for this team.') }}
        </flux:text>

        @if ($this->pairedDevices->isEmpty())
            <div class="flex gap-3 rounded-lg bg-zinc-50 p-4 dark:bg-white/5">
                <flux:icon.device-tablet variant="outline" class="mt-0.5 size-5 shrink-0 text-zinc-400" />
                <div>
                    <flux:heading>{{ __('No paired displays yet') }}</flux:heading>
                    <flux:text class="text-zinc-500 dark:text-zinc-400">
                        {{ __('Open :url on the tablet, then scan its QR code with your phone.', ['url' => preg_replace('#^https?://#', '', route('kiosk.index'))]) }}
                    </flux:text>
                </div>
            </div>
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
                        @if ($this->canUpdateTeam)
                            <flux:button
                                size="sm"
                                variant="ghost"
                                wire:click="forget({{ $device->id }})"
                                wire:confirm="{{ __('Forget this display? It will return to the QR pairing screen.') }}"
                            >
                                {{ __('Forget') }}
                            </flux:button>
                        @endif
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
