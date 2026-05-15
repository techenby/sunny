<?php

use App\Actions\Calendars\FetchCalendarEvents;
use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::kiosk')] class extends Component
{
    public string $weekStartDate = '';

    public string $format = 'week';

    public array $selectedFeeds = [];

    public function mount(): void
    {
        $this->weekStartDate = CarbonImmutable::now($this->timezoneName())
            ->startOfWeek(Auth::user()->currentTeam->week_start)
            ->toDateString();

        $this->selectedFeeds = $this->feeds->pluck('id')->toArray();
    }

    /** @return EloquentCollection<int, CalendarFeed> */
    #[Computed]
    public function feeds(): EloquentCollection
    {
        return Auth::user()
            ->calendarFeeds()
            ->get();
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function weekEvents(): array
    {
        return $this->feeds
            ->whereIn('id', $this->selectedFeeds)
            ->flatMap(function (CalendarFeed $feed) {
                try {
                    return resolve(FetchCalendarEvents::class)->handle($feed, 7, $this->weekStartsAt());
                } catch (Throwable) {
                    return collect();
                }
            })
            ->sortBy('starts_at')
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
    public function nowLabel(): string
    {
        return CarbonImmutable::now($this->timezoneName())->format('D, M j g:i A');
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
            ->startOfWeek(Auth::user()->currentTeam->week_start);
    }

    private function timezoneName(): string
    {
        return Auth::user()->currentTeam->timezone ?: 'America/Chicago';
    }
};
