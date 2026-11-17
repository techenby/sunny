<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ShowsCalendarEvents;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySync;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Http\Integrations\Sunny\SunnyTokenStore;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Support\CalendarAgenda;
use App\Support\Toast;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Native\Mobile\AsyncTask;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Exceptions\AsyncTaskException;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;

class Today extends Component
{
    use ChecksSunnySync;
    use ShowsCalendarEvents;

    public string $date = '';

    public bool $loading = false;

    public string $loadError = '';

    public ?int $justTicked = null;

    public function mount(): void
    {
        $this->refreshCalendar();
    }

    public function onResume(): void
    {
        $this->refreshLocalSyncedData();
        $this->refreshCalendar();
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    #[Computed]
    public function events(): ?array
    {
        if ($this->calendarSnapshot === null || ($this->calendarFeeds['feeds'] ?? null) === []) {
            return null;
        }

        return CalendarAgenda::rows($this->calendarSnapshot['events']);
    }

    /**
     * @return list<array{id: int, name: string, summary: string, owner: array{name: string, initials: string|null, whose: string|null}|null, timeOfDay: TimeOfDay, steps: list<array{id: int, name: string, completed: bool}>}>
     */
    #[Computed]
    public function routines(): array
    {
        $team = app(SunnyTeam::class)->current();
        $day = $this->day();

        if ($team === null || $day === null) {
            return [];
        }

        return RoutineOccurrence::forActiveTeam()
            ->where('due_on', $day->toDateString())
            ->where(fn (Builder $query) => $query
                ->whereNotIn('routine_id', Routine::forCurrentServer()->whereDate('starts_on', '>', $day->toDateString())->select('id'))
                ->orWhereHas('steps', fn (Builder $query) => $query->whereNotNull('completed_at')))
            ->with('steps', 'routine')
            ->get()
            ->sortBy(fn (RoutineOccurrence $occurrence): array => [$occurrence->time_of_day->sortOrder(), $occurrence->name])
            ->map(function (RoutineOccurrence $occurrence) use ($team): array {
                $owner = $team->owner($occurrence->routine?->user_id);
                $completed = $occurrence->steps->filter->isCompleted()->count();
                $total = $occurrence->steps->count();

                return [
                    'id' => $occurrence->id,
                    'name' => $occurrence->name,
                    'summary' => collect([$owner['whose'] ?? null, $occurrence->time_of_day->label(), $total ? "{$completed} of {$total}" : null])->filter()->join(' · '),
                    'owner' => $owner,
                    'timeOfDay' => $occurrence->time_of_day,
                    'steps' => $occurrence->steps->map(fn (RoutineOccurrenceStep $step): array => [
                        'id' => $step->id,
                        'name' => $step->name,
                        'completed' => $step->isCompleted(),
                    ])->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{title: string, date: string, possessive: string, empty: string, emptyDetail: string}
     */
    #[Computed]
    public function labels(): array
    {
        $day = $this->day();
        $today = $this->today();

        if ($day === null || $today === null) {
            return ['title' => 'Today', 'date' => '', 'possessive' => 'today’s', 'empty' => 'No routines today', 'emptyDetail' => ''];
        }

        $offset = (int) round($today->diffInDays($day));
        $named = match ($offset) {
            0 => 'Today',
            1 => 'Tomorrow',
            -1 => 'Yesterday',
            default => null,
        };
        $nearby = abs($offset) < 7;
        $title = $named ?? ($nearby ? $day->format('l') : $day->format('F j'));
        $relative = $offset < 0 ? abs($offset).' days ago' : 'in '.$offset.' days';

        return [
            'title' => $title,
            'date' => match (true) {
                $named !== null => $day->format('l, F j'),
                $nearby => $day->format('F j').' · '.$relative,
                $day->year === $today->year => $day->format('l').' · '.$relative,
                default => $day->format('l, Y').' · '.$relative,
            },
            'possessive' => ($named !== null ? strtolower($named) : $title).'’s',
            'empty' => $named !== null ? 'No routines '.strtolower($named) : 'No routines on '.$title,
            'emptyDetail' => match (true) {
                $offset === 0 => 'Routines due today will show up here. Pull down on the summary to refresh.',
                $offset < 0 => 'No routines were scheduled on this day.',
                default => 'No routines are scheduled on this day.',
            },
        ];
    }

    #[Computed]
    public function isToday(): bool
    {
        $today = $this->today();

        return $today === null || $this->day()->isSameDay($today);
    }

    #[Computed]
    public function canGoBack(): bool
    {
        $day = $this->day();

        return $day !== null && $day->greaterThan($this->today()->subDays(SunnyStore::ROUTINE_DAYS_KEPT));
    }

    #[Computed]
    public function canGoForward(): bool
    {
        $day = $this->day();

        return $day !== null && $day->lessThan($this->today()->addDays(SunnyStore::ROUTINE_DAYS_KEPT + 1));
    }

    public function previousDay(): void
    {
        if ($this->canGoBack) {
            $this->showDay($this->day()->subDay());
        }
    }

    public function nextDay(): void
    {
        if ($this->canGoForward) {
            $this->showDay($this->day()->addDay());
        }
    }

    public function showToday(): void
    {
        if ($today = $this->today()) {
            $this->showDay($today);
        }
    }

    public function retry(): void
    {
        $this->loadDay();
        $this->refreshCalendar(force: true);
    }

    public function toggleStep(int $id): void
    {
        $step = collect($this->routines)->pluck('steps')->flatten(1)->firstWhere('id', $id);

        if ($step === null) {
            return;
        }

        try {
            app(SunnyOutbox::class)->queueRoutineStep($id, ! $step['completed']);
            $this->justTicked = $step['completed'] ? null : $id;
        } catch (ValidationException $exception) {
            Toast::error(collect($exception->errors())->flatten()->first());
        }

        app(SunnySyncCoordinator::class)->dispatch();
        unset($this->routines);
    }

    #[On('sunny-sync-complete')]
    public function onSyncComplete(string $status): void
    {
        if ($status !== 'finished') {
            return;
        }

        $this->refreshLocalSyncedData();
        $this->refreshCalendar();

        if (! $this->isKeptOnPhone()) {
            $this->loadDay();
        }
    }

    protected function refreshLocalSyncedData(): void
    {
        unset($this->routines, $this->labels, $this->isToday, $this->canGoBack, $this->canGoForward, $this->calendarSnapshot, $this->calendarFeeds, $this->calendarUpdatedAt, $this->calendarIsLoading, $this->events);
    }

    public function render(): View
    {
        return view('native.today');
    }

    private function showDay(CarbonImmutable $day): void
    {
        $this->date = $day->isSameDay($this->today()) ? '' : $day->toDateString();
        $this->justTicked = null;
        $this->calendarStatus = '';
        $this->refreshLocalSyncedData();
        $this->loadDay();
        $this->refreshCalendar();
    }

    private function loadDay(): void
    {
        $this->loadError = '';
        $this->loading = false;
        $team = app(SunnyTeam::class)->current();

        if ($team === null || $this->isKeptOnPhone()) {
            return;
        }

        $teamId = $team->id;
        $date = $this->day()->toDateString();

        try {
            $token = app(SunnyTokenStore::class)->get();
        } catch (RuntimeException) {
            $token = null;
        }

        $this->loading = ! app(SunnyStore::class)->hasRoutineDay($teamId, $date);

        AsyncTask::dispatch(static fn () => app(SunnySync::class)->fetchRoutineDay($teamId, $date, $token))
            ->finished(function () use ($date): void {
                if ($this->day()?->toDateString() !== $date) {
                    return;
                }

                $this->loading = false;
                $this->loadError = '';
                unset($this->routines);
            })
            ->failed(function (AsyncTaskException $exception) use ($teamId, $date): void {
                if ($this->day()?->toDateString() !== $date) {
                    return;
                }

                $this->loading = false;

                if (! app(SunnyStore::class)->hasRoutineDay($teamId, $date)) {
                    $this->loadError = is_a($exception->originalClass(), FatalRequestException::class, true) ? 'offline' : 'unavailable';
                }
            });
    }

    protected function calendarRange(): ?array
    {
        $team = app(SunnyTeam::class)->current();
        $day = $this->day();

        return $team === null || $day === null ? null : [$team->id, $day->toDateString(), 1];
    }

    private function isKeptOnPhone(): bool
    {
        $day = $this->day();
        $today = $this->today();

        return $day === null || $day->isSameDay($today) || $day->isSameDay($today->addDay());
    }

    private function day(): ?CarbonImmutable
    {
        $today = $this->today();

        if ($today === null || $this->date === '') {
            return $today;
        }

        return CarbonImmutable::parse($this->date, $today->getTimezone())->startOfDay();
    }

    private function today(): ?CarbonImmutable
    {
        return app(SunnyTeam::class)->current()?->today();
    }
}
