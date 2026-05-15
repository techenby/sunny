<?php

use App\Actions\Calendars\FetchCalendarEvents;
use App\Models\CalendarFeed;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::kiosk')] class extends Component
{
    #[Url]
    public string $focusedDate = '';

    #[Url]
    public string $format = 'week';

    public array $selectedFeeds = [];

    public function mount(): void
    {
        $this->focusedDate = CarbonImmutable::now($this->timezoneName())->toDateString();

        $this->selectedFeeds = $this->feeds->pluck('id')->toArray();
    }

    /** @return EloquentCollection<int, CalendarFeed> */
    #[Computed]
    public function feeds(): EloquentCollection
    {
        return Auth::user()->currentTeam
            ->calendarFeeds()
            ->get();
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function dayEvents(): array
    {
        return $this->eventsForRange($this->focusedDate(), 1);
    }

    /** @return array{date: CarbonImmutable, events: array<int, array<string, mixed>>, is_today: bool} */
    #[Computed]
    public function day(): array
    {
        $date = $this->focusedDate();

        return [
            'date' => $date,
            'events' => $this->dayEvents,
            'is_today' => $date->isSameDay(CarbonImmutable::now($this->timezoneName())),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function weekEvents(): array
    {
        return $this->eventsForRange($this->weekStartsAt(), 7);
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

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function monthEvents(): array
    {
        return $this->eventsForRange($this->monthGridStartsAt(), 42);
    }

    /** @return array<int, array{date: CarbonImmutable, events: array<int, array<string, mixed>>, is_today: bool, is_current_month: bool}> */
    #[Computed]
    public function monthDays(): array
    {
        $events = collect($this->monthEvents);
        $month = $this->monthStartsAt();

        return collect(range(0, 41))
            ->map(function (int $offset) use ($events, $month): array {
                $date = $this->monthGridStartsAt()->addDays($offset);

                return [
                    'date' => $date,
                    'events' => $events
                        ->filter(fn (array $event) => $event['starts_at']->isSameDay($date))
                        ->values()
                        ->all(),
                    'is_today' => $date->isSameDay(CarbonImmutable::now($this->timezoneName())),
                    'is_current_month' => $date->isSameMonth($month),
                ];
            })
            ->all();
    }

    #[Computed]
    public function nowLabel(): string
    {
        return CarbonImmutable::now($this->timezoneName())->format('D, M j g:i A');
    }

    public function previous(): void
    {
        $this->focusedDate = match ($this->format) {
            'day' => $this->focusedDate()->subDay()->toDateString(),
            'month' => $this->focusedDate()->subMonthNoOverflow()->toDateString(),
            default => $this->weekStartsAt()->subWeek()->toDateString(),
        };

        $this->clearCalendarState();
    }

    public function next(): void
    {
        $this->focusedDate = match ($this->format) {
            'day' => $this->focusedDate()->addDay()->toDateString(),
            'month' => $this->focusedDate()->addMonthNoOverflow()->toDateString(),
            default => $this->weekStartsAt()->addWeek()->toDateString(),
        };

        $this->clearCalendarState();
    }

    public function current(): void
    {
        $this->focusedDate = CarbonImmutable::now($this->timezoneName())->toDateString();

        $this->clearCalendarState();
    }

    private function weekStartsAt(): CarbonImmutable
    {
        return $this->focusedDate()
            ->startOfWeek(Auth::user()->currentTeam->week_start);
    }

    private function monthStartsAt(): CarbonImmutable
    {
        return $this->focusedDate()->startOfMonth();
    }

    private function monthGridStartsAt(): CarbonImmutable
    {
        return $this->monthStartsAt()
            ->startOfWeek(Auth::user()->currentTeam->week_start);
    }

    private function focusedDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->focusedDate, $this->timezoneName());
    }

    /** @return array<int, array<string, mixed>> */
    private function eventsForRange(CarbonImmutable $startsAt, int $days): array
    {
        return $this->feeds
            ->whereIn('id', $this->selectedFeeds)
            ->flatMap(function (CalendarFeed $feed) use ($days, $startsAt) {
                return resolve(FetchCalendarEvents::class)->handle($feed, $days, $startsAt);
            })
            ->sortBy('starts_at')
            ->values()
            ->all();
    }

    private function clearCalendarState(): void
    {
        unset($this->dayEvents, $this->day, $this->weekEvents, $this->weekDays, $this->monthEvents, $this->monthDays);
    }

    private function timezoneName(): string
    {
        return Auth::user()->currentTeam->timezone ?: 'America/Chicago';
    }
};
