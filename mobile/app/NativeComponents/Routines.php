<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Routine;
use App\Models\RoutineStep;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;

class Routines extends Component
{
    use ChecksSunnySync;

    public string $search = '';

    /**
     * @return array{id: int, team_id: int, user_id: int|null, name: string, time_of_day: TimeOfDay, frequency: RoutineFrequency, weekdays: list<int>|null, day_of_month: int|null, starts_on: string|null, is_active: bool, summary: string}|null
     */
    public static function find(int $id): ?array
    {
        $routine = Routine::forActiveTeam()->where(fn ($query) => $query->whereKey($id)->orWhere('local_id', $id))->first();

        return $routine === null ? null : [
            ...$routine->toArray(),
            'time_of_day' => $routine->time_of_day,
            'frequency' => $routine->frequency,
            'summary' => self::summary($routine),
        ];
    }

    /**
     * @return list<array{id: int, routine_id: int, name: string, position: int, created_at: string, updated_at: string}>
     */
    public static function stepsOf(int $routineId): array
    {
        return RoutineStep::forCurrentServer()->where('routine_id', $routineId)->orderBy('position')->orderBy('id')->get()->toArray();
    }

    /**
     * @return list<array{id: int, name: string, timeOfDay: TimeOfDay, summary: string, owner: array{name: string, initials: string|null, whose: string|null}|null}>
     */
    #[Computed]
    public function routines(): array
    {
        $team = app(SunnyTeam::class)->current();

        return Routine::forActiveTeam()
            ->withCount('steps')
            ->get()
            ->filter(fn (Routine $routine): bool => $this->search === '' || Str::contains($routine->name, $this->search, ignoreCase: true))
            ->sortBy(fn (Routine $routine): array => [$routine->time_of_day->sortOrder(), $routine->name])
            ->map(function (Routine $routine) use ($team): array {
                $owner = $team?->owner($routine->user_id);

                return [
                    'id' => $routine->id,
                    'name' => $routine->name,
                    'timeOfDay' => $routine->time_of_day,
                    'owner' => $owner,
                    'summary' => collect([$owner['whose'] ?? null, self::summary($routine), trans_choice(':count step|:count steps', $routine->steps_count)])->filter()->join(' · '),
                ];
            })
            ->values()
            ->all();
    }

    public function updateSearch(string $query): void
    {
        $this->search = trim($query);
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
        unset($this->routines);
    }

    public function render(): View
    {
        return view('native.routines');
    }

    private static function summary(Routine $routine): string
    {
        return $routine->time_of_day->label().' · '.$routine->scheduleSummary().($routine->is_active ? '' : ' · Paused');
    }
}
