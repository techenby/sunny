<x-dashboard.card :heading="__('Coming up')" :action="__('Calendars')" :href="route('kiosk.configure.calendar')">
    <div class="@container space-y-5">
        <div class="grid gap-5 @2xl:grid-cols-3">
            @foreach ($this->days as $day)
                <x-dashboard.day wire:key="dashboard-day-{{ $loop->index }}" :label="$day['label']">
                    @forelse ($day['events'] as $event)
                        <x-dashboard.event
                            wire:key="dashboard-event-{{ $event['feed_id'] }}-{{ $event['starts_at']->timestamp }}-{{ str($event['title'])->slug() }}"
                            :time="$this->eventTime($event)"
                            :title="$event['title']"
                            :location="$event['location']"
                            :color="$event['feed_color']"
                            :declined="($event['response_status'] ?? null) === 'DECLINED'"
                        />
                    @empty
                        <flux:text size="sm" variant="subtle">{{ __('Nothing scheduled.') }}</flux:text>
                    @endforelse
                </x-dashboard.day>
            @endforeach
        </div>

        @if ($this->feeds->isEmpty())
            <x-dashboard.more
                :href="route('kiosk.configure.calendar')"
                :label="__('Connect a calendar')"
                :note="__('No calendars connected')"
            />
        @endif
    </div>
</x-dashboard.card>
