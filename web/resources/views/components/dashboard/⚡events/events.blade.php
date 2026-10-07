<flux:card body="flush" class="flex flex-col">
    <flux:card.header>
        <flux:card.heading size="lg">{{ __('Coming up') }}</flux:card.heading>
        <flux:card.actions>
            <flux:button size="sm" variant="ghost" icon:trailing="arrow-right" :href="route('kiosk.configure.calendar')" wire:navigate>
                {{ __('Calendars') }}
            </flux:button>
        </flux:card.actions>
    </flux:card.header>

    <flux:card.body class="flex-1">
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
                                <div
                                    wire:key="dashboard-event-{{ $event['feed_id'] }}-{{ $event['starts_at']->timestamp }}-{{ str($event['title'])->slug() }}"
                                    @class([
                                        'flex gap-3 border-l-4 py-0.5 pl-3',
                                        'line-through opacity-60' => ($event['response_status'] ?? null) === 'DECLINED',
                                    ])
                                    style="border-color: {{ $event['feed_color'] }}"
                                >
                                    <flux:text size="sm" class="w-28 shrink-0 tabular-nums">{{ $this->eventTime($event) }}</flux:text>

                                    <div class="min-w-0">
                                        <flux:text variant="strong" class="truncate">{{ $event['title'] }}</flux:text>
                                        @if ($event['location'])
                                            <flux:text size="sm" class="truncate">{{ $event['location'] }}</flux:text>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <flux:text size="sm" variant="subtle">{{ __('Nothing scheduled.') }}</flux:text>
                            @endforelse
                        </section>
                    @endforeach
                </div>
            </div>
        @endif
    </flux:card.body>
</flux:card>
