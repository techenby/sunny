<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Dialog;

class Routines extends NativeComponent
{
    use ChecksSunnySync;

    /**
     * @return array{id: int, team_id: int, user_id: int|null, assignee: string|null, name: string, time_of_day: TimeOfDay, frequency: RoutineFrequency, weekdays: list<int>|null, day_of_month: int|null, is_active: bool, steps: list<array{id?: int, name: string}>|null}|null
     */
    public static function find(int $id): ?array
    {
        $routine = Routine::forActiveTeam()->where(fn ($query) => $query->whereKey($id)->orWhere('local_id', $id))->first();

        return $routine === null ? null : [...$routine->toArray(), 'time_of_day' => $routine->time_of_day, 'frequency' => $routine->frequency];
    }

    /**
     * @return list<array{id: int, name: string, assignee: string, timeOfDay: TimeOfDay, completed: int, total: int, steps: list<array{id: int, name: string, completed: bool}>}>
     */
    #[Computed]
    public function routines(): array
    {
        $team = app(SunnyTeam::class)->current();

        if ($team === null) {
            return [];
        }

        return RoutineOccurrence::forActiveTeam()
            ->where('due_on', $team->today()->toDateString())
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
     * Every routine in the team, for managing them rather than ticking them off.
     *
     * @return list<array{id: int, name: string, timeOfDay: TimeOfDay, summary: string}>
     */
    #[Computed]
    public function allRoutines(): array
    {
        $me = app(SunnyStore::class)->accountId();

        return Routine::forActiveTeam()
            ->get()
            ->sortBy(fn (Routine $routine): array => [$routine->time_of_day->sortOrder(), $routine->name])
            ->map(fn (Routine $routine): array => [
                'id' => $routine->id,
                'name' => $routine->name,
                'timeOfDay' => $routine->time_of_day,
                'summary' => collect([
                    $routine->scheduleSummary(),
                    match ($routine->user_id) {
                        null => 'Household',
                        $me => 'Me',
                        default => $routine->assignee,
                    },
                    $routine->is_active ? null : 'Paused',
                ])->filter()->join(' · '),
            ])
            ->values()
            ->all();
    }

    #[Computed]
    public function heading(): string
    {
        return app(SunnyTeam::class)->current()?->today()->format('l, F j') ?? '';
    }

    public function toggleStep(int $id): void
    {
        $step = collect($this->routines)->pluck('steps')->flatten(1)->firstWhere('id', $id);

        if ($step === null) {
            return;
        }

        try {
            app(SunnyOutbox::class)->queueRoutineStep($id, ! $step['completed']);
        } catch (ValidationException $exception) {
            Dialog::toast(collect($exception->errors())->flatten()->first());
        }

        app(SunnySyncCoordinator::class)->dispatch();
        unset($this->routines);
    }

    #[On('sunny-sync-complete')]
    public function onSyncComplete(string $status): void
    {
        if ($status === 'finished') {
            $this->refreshLocalSyncedData();
        }
    }

    protected function refreshLocalSyncedData(): void
    {
        unset($this->routines, $this->allRoutines, $this->heading);
    }

    public function render(): View
    {
        return view('native.routines');
    }
}
