<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ShowsCalendarEvents;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Support\CalendarAgenda;
use Carbon\CarbonImmutable;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;

class Calendar extends Component
{
    use ChecksSunnySync;
    use ShowsCalendarEvents;

    public function mount(): void
    {
        $this->refreshCalendar();
    }

    public function onResume(): void
    {
        $this->refreshLocalSyncedData();
        $this->refreshCalendar();
    }

    public function refresh(): void
    {
        $this->refreshCalendar(force: true);
    }

    /**
     * @return list<array{key: string, name: string, detail: string, events: list<array<string, mixed>>}>
     */
    #[Computed]
    public function days(): array
    {
        $today = app(SunnyTeam::class)->current()?->today();

        if ($this->calendarSnapshot === null || $today === null) {
            return [];
        }

        return collect(CalendarAgenda::byDay($this->calendarSnapshot['events'], $today))
            ->map(function (array $events, string $date) use ($today): array {
                $day = CarbonImmutable::parse($date, $today->getTimezone());
                $name = CalendarAgenda::dayName($day, $today);

                return [
                    'key' => $date,
                    'name' => $name,
                    'detail' => in_array($name, ['Today', 'Tomorrow'], true) ? $day->format('l, M j') : $day->format('M j'),
                    'events' => CalendarAgenda::rows($events),
                ];
            })
            ->values()
            ->all();
    }

    #[Computed]
    public function updatedLabel(): ?string
    {
        $fetchedAt = $this->calendarSnapshot['fetched_at'] ?? null;

        if ($fetchedAt === null) {
            return null;
        }

        return 'Updated '.($fetchedAt->greaterThan(now()->subMinute()) ? 'just now' : $fetchedAt->diffForHumans()).' · next 6 weeks';
    }

    #[Computed]
    public function hasNoCalendars(): bool
    {
        return ($this->calendarFeeds['feeds'] ?? null) === [];
    }

    public function render(): View
    {
        return view('native.calendar');
    }

    protected function calendarRange(): ?array
    {
        $team = app(SunnyTeam::class)->current();

        return $team === null ? null : [$team->id, $team->today()->toDateString(), 42];
    }

    protected function refreshLocalSyncedData(): void
    {
        unset($this->calendarSnapshot, $this->calendarFeeds, $this->calendarUpdatedAt, $this->days, $this->updatedLabel, $this->hasNoCalendars);
    }
}
