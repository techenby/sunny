<x-dashboard.card :heading="__('Coming up')" :action="__('Calendars')" :href="route('kiosk.configure.calendar')">
    @if ($this->feeds->isEmpty())
        <div class="py-6 text-center">
            <flux:icon name="calendar" class="mx-auto mb-3 size-10 text-zinc-400" />
            <flux:text>{{ __('Subscribe to a calendar to see your family\'s events here.') }}</flux:text>
            <flux:button size="sm" class="mt-4" icon="plus" :href="route('kiosk.configure.calendar')" wire:navigate>
                {{ __('Add a calendar') }}
            </flux:button>
        </div>
    @else
        <div class="@container">
            <div class="grid gap-5 @2xl:grid-cols-3">
                @foreach ($this->days as $day)
                    <section wire:key="dashboard-day-{{ $loop->index }}" class="space-y-2">
                        <flux:heading size="sm" class="text-zinc-500 dark:text-zinc-400">{{ $day['label'] }}</flux:heading>

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
                    </section>
                @endforeach
            </div>
        </div>
    @endif
</x-dashboard.card>
