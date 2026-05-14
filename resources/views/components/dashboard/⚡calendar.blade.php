<?php

use App\Actions\Calendars\FetchCalendarEvents;
use App\Models\CalendarFeed;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    private const DEFAULT_COLOR = '#2563eb';

    private const COLOR_OPTIONS = [
        '#2563eb' => 'Blue',
        '#16a34a' => 'Green',
        '#dc2626' => 'Red',
        '#9333ea' => 'Purple',
        '#ea580c' => 'Orange',
        '#0891b2' => 'Cyan',
        '#ca8a04' => 'Gold',
        '#4f46e5' => 'Indigo',
    ];

    public string $feedName = '';

    public string $feedUrl = '';

    public string $feedColor = self::DEFAULT_COLOR;

    public string $weekStartDate = '';

    public function mount(): void
    {
        $this->weekStartDate = CarbonImmutable::now($this->timezoneName())
            ->startOfWeek(CarbonInterface::SUNDAY)
            ->toDateString();
    }

    /** @return EloquentCollection<int, CalendarFeed> */
    #[Computed]
    public function feeds(): EloquentCollection
    {
        return Auth::user()
            ->calendarFeeds()
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function weekEvents(): array
    {
        $weekStartsAt = $this->weekStartsAt();

        return $this->feeds
            ->flatMap(function (CalendarFeed $feed) {
                try {
                    return app(FetchCalendarEvents::class)->handle($feed, 7, $this->weekStartsAt());
                } catch (Throwable) {
                    return collect();
                }
            })
            ->sortBy('starts_at')
            ->take(12)
            ->values()
            ->all();
    }

    /** @return array<int, array{date: CarbonImmutable, events: array<int, array<string, mixed>>, is_today: bool}> */
    #[Computed]
    public function weekDays(): array
    {
        $events = collect($this->weekEvents);

        return collect(range(0, 6))
            ->map(function (int $offset) use ($events): array {
                $date = $this->weekStartsAt()->addDays($offset);

                return [
                    'date' => $date,
                    'events' => $events
                        ->filter(fn (array $event) => $event['starts_at']->isSameDay($date))
                        ->values()
                        ->all(),
                    'is_today' => $date->isSameDay(CarbonImmutable::now($this->timezoneName())),
                ];
            })
            ->all();
    }

    #[Computed]
    public function weekLabel(): string
    {
        $weekStartsAt = $this->weekStartsAt();
        $weekEndsAt = $weekStartsAt->addDays(6);

        if ($weekStartsAt->isSameMonth($weekEndsAt)) {
            return $weekStartsAt->format('M j') . ' - ' . $weekEndsAt->format('j, Y');
        }

        return $weekStartsAt->format('M j') . ' - ' . $weekEndsAt->format('M j, Y');
    }

    /** @return array<string, string> */
    public function colorOptions(): array
    {
        return self::COLOR_OPTIONS;
    }

    public function addFeed(): void
    {
        $this->feedUrl = rtrim($this->feedUrl, " \t\n\r\0\x0B,");

        $validated = $this->validate([
            'feedName' => ['nullable', 'string', 'max:255'],
            'feedUrl' => ['required', 'url', 'starts_with:http://,https://', 'max:2048'],
            'feedColor' => ['required', Rule::in(array_keys(self::COLOR_OPTIONS))],
        ]);

        $url = $validated['feedUrl'];
        $host = parse_url($url, PHP_URL_HOST);

        Auth::user()->calendarFeeds()->create([
            'name' => filled($validated['feedName']) ? $validated['feedName'] : Str::headline((string) $host),
            'url' => $url,
            'color' => $validated['feedColor'],
        ]);

        $this->reset('feedName', 'feedUrl');
        $this->feedColor = self::DEFAULT_COLOR;
        unset($this->feeds, $this->weekEvents, $this->weekDays);

        Flux::toast(__('Calendar feed added.'));
    }

    public function updateFeedColor(int $feedId, string $color): void
    {
        validator(['color' => $color], [
            'color' => [Rule::in(array_keys(self::COLOR_OPTIONS))],
        ])->validate();

        Auth::user()->calendarFeeds()->whereKey($feedId)->update(['color' => $color]);

        unset($this->feeds, $this->weekEvents, $this->weekDays);
    }

    public function deleteFeed(int $feedId): void
    {
        Auth::user()->calendarFeeds()->whereKey($feedId)->delete();

        unset($this->feeds, $this->weekEvents, $this->weekDays);

        Flux::toast(__('Calendar feed removed.'));
    }

    public function previousWeek(): void
    {
        $this->weekStartDate = $this->weekStartsAt()->subWeek()->toDateString();
        unset($this->weekEvents, $this->weekDays, $this->weekLabel);
    }

    public function nextWeek(): void
    {
        $this->weekStartDate = $this->weekStartsAt()->addWeek()->toDateString();
        unset($this->weekEvents, $this->weekDays, $this->weekLabel);
    }

    public function currentWeek(): void
    {
        $this->weekStartDate = CarbonImmutable::now($this->timezoneName())
            ->startOfWeek(CarbonInterface::SUNDAY)
            ->toDateString();

        unset($this->weekEvents, $this->weekDays, $this->weekLabel);
    }

    private function weekStartsAt(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->weekStartDate, $this->timezoneName())
            ->startOfWeek(CarbonInterface::SUNDAY);
    }

    private function timezoneName(): string
    {
        return Auth::user()->timezone ?: 'America/Chicago';
    }
};
?>

<section class="flex w-full flex-col gap-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <flux:heading size="xl">{{ __('Calendar') }}</flux:heading>
            <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">
                {{ __('Weekly events from your subscribed calendars') }}
            </flux:text>
        </div>

        <form wire:submit="addFeed" class="grid gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900 sm:grid-cols-[minmax(0,12rem)_minmax(16rem,1fr)_auto] lg:w-[48rem]">
            <flux:input wire:model="feedName" :label="__('Name')" :placeholder="__('Family')" />
            <flux:input wire:model="feedUrl" :label="__('Feed URL')" type="url" :placeholder="__('https://.../calendar.ics')" />
            <div class="flex items-end">
                <flux:button type="submit" variant="primary" icon="plus" class="w-full sm:w-auto">
                    {{ __('Add') }}
                </flux:button>
            </div>
            <fieldset class="sm:col-span-3">
                <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Color') }}</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->colorOptions() as $color => $label)
                        <label class="relative cursor-pointer">
                            <input wire:model="feedColor" type="radio" value="{{ $color }}" class="peer sr-only" aria-label="{{ __($label) }}" />
                            <span
                                class="block size-7 rounded-full border border-black/10 ring-offset-2 peer-checked:ring-2 peer-checked:ring-zinc-900 dark:border-white/15 dark:ring-offset-zinc-900 dark:peer-checked:ring-white"
                                style="background-color: {{ $color }}"
                            ></span>
                        </label>
                    @endforeach
                </div>
                <flux:error name="feedColor" />
            </fieldset>
        </form>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="overflow-hidden rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex flex-col gap-3 border-b border-zinc-200 px-5 py-4 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <flux:heading size="lg">{{ __('Week') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->weekLabel }}</flux:text>
                </div>

                <div class="flex items-center gap-2">
                    <flux:button type="button" variant="ghost" size="sm" icon="chevron-left" wire:click="previousWeek" />
                    <flux:button type="button" variant="filled" size="sm" wire:click="currentWeek">{{ __('Today') }}</flux:button>
                    <flux:button type="button" variant="ghost" size="sm" icon="chevron-right" wire:click="nextWeek" />
                </div>
            </div>

            @if ($this->feeds->isEmpty())
                <div class="px-5 py-12 text-center">
                    <flux:icon name="calendar-days" class="mx-auto mb-3 size-8 text-zinc-400" />
                    <flux:text class="text-zinc-500 dark:text-zinc-400">
                        {{ __('Add a calendar feed to see weekly events.') }}
                    </flux:text>
                </div>
            @else
                <div class="grid min-h-[32rem] divide-y divide-zinc-100 dark:divide-zinc-700 lg:grid-cols-7 lg:divide-x lg:divide-y-0">
                    @foreach ($this->weekDays as $day)
                        <div wire:key="calendar-day-{{ $day['date']->toDateString() }}" class="flex min-h-40 flex-col">
                            <div @class([
                                'border-b px-3 py-3 border-zinc-100 dark:border-zinc-700',
                                'bg-zinc-50 dark:bg-zinc-800' => ! $day['is_today'],
                                'bg-blue-100 dark:bg-blue-900' => $day['is_today'],
                            ])>
                                <div>
                                    <div @class([
                                        'text-xs font-medium uppercase',
                                        'text-zinc-500 dark:text-zinc-400'
                                    ])>{{ $day['date']->format('D') }}</div>
                                    <div @class([
                                        'text-sm font-semibold',
                                        'text-zinc-900 dark:text-zinc-100'
                                    ])>{{ $day['date']->format('M j') }}</div>
                                </div>
                            </div>

                            <div class="flex flex-1 flex-col gap-2 p-3">
                                @forelse ($day['events'] as $event)
                                    <div
                                        wire:key="calendar-event-{{ $event['feed_id'] }}-{{ $event['starts_at']->timestamp }}-{{ str($event['title'])->slug() }}"
                                        class="rounded-md border border-zinc-200 bg-white p-2 text-sm shadow-xs dark:border-zinc-700 dark:bg-zinc-950"
                                        style="border-left: 4px solid {{ $event['feed_color'] }}"
                                    >
                                        <div class="mb-1 flex items-center gap-1.5 text-xs font-medium text-zinc-500 dark:text-zinc-400">
                                            <span class="size-2 rounded-full" style="background-color: {{ $event['feed_color'] }}"></span>
                                            {{ $event['all_day'] ? __('All day') : $event['starts_at']->format('g:i A') }}
                                        </div>

                                        <div class="break-words font-medium leading-snug text-zinc-900 dark:text-zinc-100">{{ $event['title'] }}</div>
                                        <div class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $event['feed_name'] }}</div>

                                        @if ($event['location'])
                                            <div class="mt-1 truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $event['location'] }}</div>
                                        @endif
                                    </div>
                                @empty
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-5 py-4 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Feeds') }}</flux:heading>
            </div>

            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($this->feeds as $feed)
                    <div wire:key="calendar-feed-{{ $feed->id }}" class="flex items-center justify-between gap-3 px-5 py-4">
                        <div class="min-w-0">
                            <div class="flex min-w-0 items-center gap-2">
                                <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $feed->color }}"></span>
                                <div class="truncate font-medium text-zinc-900 dark:text-zinc-100">{{ $feed->name }}</div>
                            </div>
                            <div class="truncate text-sm text-zinc-500 dark:text-zinc-400">{{ parse_url($feed->url, PHP_URL_HOST) }}</div>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($this->colorOptions() as $color => $label)
                                    <flux:tooltip :content="__($label)">
                                        <button
                                            type="button"
                                            wire:click="updateFeedColor({{ $feed->id }}, '{{ $color }}')"
                                            class="size-5 rounded-full border border-black/10 ring-offset-2 {{ $feed->color === $color ? 'ring-2 ring-zinc-900 dark:ring-white' : '' }} dark:border-white/15 dark:ring-offset-zinc-900"
                                            style="background-color: {{ $color }}"
                                            aria-label="{{ __('Set :feed to :color', ['feed' => $feed->name, 'color' => __($label)]) }}"
                                        ></button>
                                    </flux:tooltip>
                                @endforeach
                            </div>
                        </div>

                        <flux:tooltip :content="__('Remove feed')">
                            <flux:button
                                type="button"
                                variant="ghost"
                                size="sm"
                                icon="trash"
                                wire:click="deleteFeed({{ $feed->id }})"
                                wire:confirm="{{ __('Remove this calendar feed?') }}"
                            />
                        </flux:tooltip>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center">
                        <flux:text class="text-zinc-500 dark:text-zinc-400">{{ __('No feeds yet') }}</flux:text>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</section>
