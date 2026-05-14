<?php

use App\Actions\Calendars\FetchCalendarEvents;
use App\Models\CalendarFeed;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Rule;
use Livewire\Component;

new #[Layout('layouts::kiosk')] class extends Component
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
                    return resolve(FetchCalendarEvents::class)->handle($feed, 7, $this->weekStartsAt());
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
