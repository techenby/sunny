<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
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

    public string $date = '';

    public bool $loading = false;

    public string $loadError = '';

    public bool $choosingDay = false;

    public ?int $justTicked = null;

    /**
     * @return list<array{id: int, name: string, assignee: string, timeOfDay: TimeOfDay, completed: int, total: int, steps: list<array{id: int, name: string, completed: bool}>}>
     */
    #[Computed]
    public function routines(): array
    {
        $day = $this->day();

        if ($day === null) {
            return [];
        }

        return RoutineOccurrence::forActiveTeam()
            ->where('due_on', $day->toDateString())
            ->where(fn (Builder $query) => $query
                ->whereNotIn('routine_id', Routine::forCurrentServer()->whereDate('starts_on', '>', $day->toDateString())->select('id'))
                ->orWhereHas('steps', fn (Builder $query) => $query->whereNotNull('completed_at')))
            ->with('steps')
            ->get()
            ->sortBy(fn (RoutineOccurrence $occurrence): array => [$occurrence->time_of_day->sortOrder(), $occurrence->name])
            ->map(fn (RoutineOccurrence $occurrence): array => [
                'id' => $occurrence->id,
                'name' => $occurrence->name,
                'assignee' => $occurrence->assignee ?? 'Household',
                'timeOfDay' => $occurrence->time_of_day,
                'completed' => $occurrence->steps->filter->isCompleted()->count(),
                'total' => $occurrence->steps->count(),
                'steps' => $occurrence->steps->map(fn (RoutineOccurrenceStep $step): array => [
                    'id' => $step->id,
                    'name' => $step->name,
                    'completed' => $step->isCompleted(),
                ])->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{title: string, date: string, bar: string, barDetail: string, possessive: string, empty: string, emptyDetail: string}
     */
    #[Computed]
    public function labels(): array
    {
        $day = $this->day();
        $today = $this->today();

        if ($day === null || $today === null) {
            return ['title' => 'Today', 'date' => '', 'bar' => 'Today', 'barDetail' => '', 'possessive' => 'today’s', 'empty' => 'No routines today', 'emptyDetail' => ''];
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
        $days = abs($offset) === 1 ? '1 day' : abs($offset).' days';

        return [
            'title' => $title,
            'date' => match (true) {
                $named !== null => $day->format('l, F j'),
                $nearby => $day->format('F j'),
                $day->year === $today->year => $day->format('l'),
                default => $day->format('l, Y'),
            },
            'bar' => $named ?? $day->format('D, M j'),
            'barDetail' => $named !== null ? $day->format('D, M j') : ($offset < 0 ? $days.' ago' : 'in '.$days),
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
    public function timezone(): string
    {
        return ($this->today() ?? CarbonImmutable::now())->getTimezone()->getName();
    }

    public function previousDay(): void
    {
        if ($day = $this->day()) {
            $this->showDay($day->subDay());
        }
    }

    public function nextDay(): void
    {
        if ($day = $this->day()) {
            $this->showDay($day->addDay());
        }
    }

    public function showToday(): void
    {
        if ($today = $this->today()) {
            $this->showDay($today);
        }
    }

    public function openDayPicker(): void
    {
        $this->choosingDay = true;
    }

    public function closeDayPicker(): void
    {
        $this->choosingDay = false;
    }

    public function pickDay(string $value): void
    {
        $this->choosingDay = false;
        $today = $this->today();

        if ($today === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return;
        }

        $this->showDay(CarbonImmutable::parse($value, $today->getTimezone()));
    }

    public function retry(): void
    {
        $this->loadDay();
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

        if (! $this->isKeptOnPhone()) {
            $this->loadDay();
        }
    }

    protected function refreshLocalSyncedData(): void
    {
        unset($this->routines, $this->labels, $this->isToday, $this->timezone);
    }

    public function render(): View
    {
        return view('native.today');
    }

    private function showDay(CarbonImmutable $day): void
    {
        $this->date = $day->isSameDay($this->today()) ? '' : $day->toDateString();
        $this->justTicked = null;
        $this->refreshLocalSyncedData();
        $this->loadDay();
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
