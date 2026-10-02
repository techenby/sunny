<?php

namespace App\Concerns;

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\SunnyStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Native\Mobile\Attributes\Computed;

/**
 * The routine form's state and rules, shared by the create and edit screens.
 *
 * Mirrors App\Livewire\Forms\Routines\RoutineForm in the sunnyhome.app web app.
 */
trait ManagesRoutineForm
{
    use SavesSunnyRecord;

    public string $name = '';

    public int $timeOfDayIndex = 0;

    public int $frequencyIndex = 0;

    /** @var list<int> */
    public array $weekdays = [];

    public string $dayOfMonth = '';

    public int $assigneeIndex = 0;

    /** Someone other than the signed-in user the routine is assigned to, kept so editing doesn't lose them. */
    public ?int $otherUserId = null;

    public ?string $otherAssignee = null;

    public bool $isActive = true;

    /** @var list<array{id?: int, name: string}> */
    public array $steps = [];

    public string $newStep = '';

    public string $error = '';

    /**
     * @param  array{team_id: int, user_id: int|null, assignee: string|null, name: string, time_of_day: TimeOfDay, frequency: RoutineFrequency, weekdays: list<int>|null, day_of_month: int|null, is_active: bool, steps: list<array{id?: int, name: string}>|null}  $routine
     */
    public function fillFromRoutine(array $routine): void
    {
        $this->initializeTeam($routine['team_id']);
        $this->name = $routine['name'];
        $this->timeOfDayIndex = (int) array_search($routine['time_of_day'], TimeOfDay::cases(), strict: true);
        $this->frequencyIndex = (int) array_search($routine['frequency'], RoutineFrequency::cases(), strict: true);
        $this->weekdays = array_map(intval(...), $routine['weekdays'] ?? []);
        $this->dayOfMonth = (string) ($routine['day_of_month'] ?? '');
        $this->isActive = $routine['is_active'];
        $this->steps = $routine['steps'] ?? [];

        if ($routine['user_id'] !== null && $routine['user_id'] !== app(SunnyStore::class)->accountId()) {
            $this->otherUserId = $routine['user_id'];
            $this->otherAssignee = $routine['assignee'];
        }

        $this->assigneeIndex = (int) array_search($routine['user_id'], array_column($this->assignees, 'id'), strict: true);
    }

    /** @return list<string> */
    #[Computed]
    public function timeOfDayOptions(): array
    {
        return array_map(fn (TimeOfDay $timeOfDay): string => $timeOfDay->label(), TimeOfDay::cases());
    }

    /** @return list<string> */
    #[Computed]
    public function frequencyOptions(): array
    {
        return array_map(fn (RoutineFrequency $frequency): string => $frequency->label(), RoutineFrequency::cases());
    }

    #[Computed]
    public function frequency(): RoutineFrequency
    {
        return RoutineFrequency::cases()[$this->frequencyIndex] ?? RoutineFrequency::Daily;
    }

    /**
     * Who the routine can be assigned to. The phone doesn't know the rest of
     * the team, so it offers the household, you, and whoever it's already
     * assigned to.
     *
     * @return list<array{label: string, id: int|null}>
     */
    #[Computed]
    public function assignees(): array
    {
        $me = app(SunnyStore::class)->accountId();

        return array_values(array_filter([
            ['label' => 'Household', 'id' => null],
            $me === null ? null : ['label' => 'Me', 'id' => $me],
            $this->otherUserId === null ? null : ['label' => $this->otherAssignee ?? 'Someone else', 'id' => $this->otherUserId],
        ]));
    }

    /** @return array<int, string> */
    #[Computed]
    public function weekdayOptions(): array
    {
        return array_map(fn (string $day): string => Str::substr($day, 0, 3), CarbonImmutable::getDays());
    }

    public function toggleWeekday(int $day): void
    {
        $this->weekdays = in_array($day, $this->weekdays, true)
            ? array_values(array_diff($this->weekdays, [$day]))
            : [...$this->weekdays, $day];
    }

    public function toggleActive(): void
    {
        $this->isActive = ! $this->isActive;
    }

    public function addStep(string $text = ''): void
    {
        $name = trim($text !== '' ? $text : $this->newStep);

        if ($name !== '') {
            $this->steps[] = ['name' => $name];
            $this->newStep = '';
        }
    }

    public function renameStep(int $index, string $name): void
    {
        if (isset($this->steps[$index])) {
            $this->steps[$index]['name'] = $name;
        }
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);

        $this->steps = array_values($this->steps);
    }

    protected function routinePayload(): array
    {
        $frequency = $this->frequency;

        return [
            'name' => trim($this->name),
            'user_id' => $this->assignees[$this->assigneeIndex]['id'] ?? null,
            'time_of_day' => (TimeOfDay::cases()[$this->timeOfDayIndex] ?? TimeOfDay::Morning)->value,
            'frequency' => $frequency->value,
            'weekdays' => $frequency->usesWeekdays() ? collect($this->weekdays)->sort()->values()->all() : null,
            'day_of_month' => $frequency->usesDayOfMonth() ? (int) $this->dayOfMonth : null,
            'is_active' => $this->isActive,
            'steps' => collect($this->steps)
                ->map(fn (array $step): array => [...$step, 'name' => trim($step['name'])])
                ->reject(fn (array $step): bool => $step['name'] === '')
                ->values()
                ->all(),
        ];
    }

    protected function validationError(): string
    {
        $day = filter_var($this->dayOfMonth, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 31]]);

        return match (true) {
            trim($this->name) === '' => 'Give the routine a name.',
            mb_strlen(trim($this->name)) > 255 => 'The name is too long (255 characters max).',
            $this->frequency->usesWeekdays() && $this->weekdays === [] => 'Choose at least one day of the week.',
            $this->frequency->usesDayOfMonth() && $day === false => 'Choose a day of the month from 1 to 31.',
            collect($this->steps)->contains(fn (array $step): bool => mb_strlen(trim($step['name'])) > 255) => 'A step is too long (255 characters max).',
            default => '',
        };
    }
}
