<?php

namespace App\Concerns;

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Routine;
use Illuminate\Validation\ValidationException;
use Native\Mobile\Attributes\Computed;
use Throwable;

/**
 * The routine form's state and rules, shared by the create and edit screens.
 *
 * Mirrors App\Livewire\Forms\Routines\RoutineForm in the sunnyhome.app web
 * app, without the assignee and start date, which stay as Sunny has them.
 */
trait ManagesRoutineForm
{
    public string $name = '';

    /** Index into {@see TimeOfDay::cases()} — bound to the time of day selector. */
    public int $timeOfDayIndex = 0;

    /** Index into {@see RoutineFrequency::cases()} — bound to the frequency selector. */
    public int $frequencyIndex = 0;

    /** @var list<int> Days of the week, where 0 is Sunday. */
    public array $weekdays = [];

    public string $dayOfMonth = '';

    public bool $isActive = true;

    /** @var list<array{id: int|null, name: string}> */
    public array $steps = [];

    public string $error = '';

    public bool $saving = false;

    public ?int $recordTeamId = null;

    public function fillFromRoutine(Routine $routine): void
    {
        $this->recordTeamId = $routine->team_id;
        $this->name = $routine->name;
        $this->timeOfDayIndex = (int) array_search($routine->time_of_day, TimeOfDay::cases(), strict: true);
        $this->frequencyIndex = (int) array_search($routine->frequency, RoutineFrequency::cases(), strict: true);
        $this->weekdays = collect($routine->weekdays ?? [])->map(fn (mixed $day): int => (int) $day)->sort()->values()->all();
        $this->dayOfMonth = (string) ($routine->day_of_month ?? '');
        $this->isActive = $routine->is_active;
        $this->steps = $routine->steps->map(fn ($step): array => ['id' => $step->id, 'name' => $step->name])->all();
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
    public function timeOfDay(): TimeOfDay
    {
        return TimeOfDay::cases()[$this->timeOfDayIndex] ?? TimeOfDay::Morning;
    }

    #[Computed]
    public function frequency(): RoutineFrequency
    {
        return RoutineFrequency::cases()[$this->frequencyIndex] ?? RoutineFrequency::Daily;
    }

    /** @return list<list<array{day: int, name: string, selected: bool}>> */
    #[Computed]
    public function weekdayRows(): array
    {
        return collect(range(0, 6))
            ->map(fn (int $day): array => ['day' => $day, 'name' => RoutineFrequency::weekdayName($day), 'selected' => in_array($day, $this->weekdays, true)])
            ->chunk(4)
            ->map(fn ($row): array => $row->values()->all())
            ->values()
            ->all();
    }

    public function toggleWeekday(int $day): void
    {
        $this->weekdays = in_array($day, $this->weekdays, true)
            ? array_values(array_diff($this->weekdays, [$day]))
            : [...$this->weekdays, $day];
    }

    public function addStep(): void
    {
        $this->steps[] = ['id' => null, 'name' => ''];
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);

        $this->steps = array_values($this->steps);
    }

    public function moveStep(int $index, int $by): void
    {
        $target = $index + $by;

        if (! isset($this->steps[$index], $this->steps[$target])) {
            return;
        }

        [$this->steps[$index], $this->steps[$target]] = [$this->steps[$target], $this->steps[$index]];
    }

    public function setStepName(int $index, string $name): void
    {
        if (isset($this->steps[$index])) {
            $this->steps[$index]['name'] = $name;
        }
    }

    protected function routinePayload(): array
    {
        $frequency = $this->frequency;

        return [
            'name' => trim($this->name),
            'time_of_day' => $this->timeOfDay->value,
            'frequency' => $frequency->value,
            'weekdays' => $frequency === RoutineFrequency::Weekly ? collect($this->weekdays)->sort()->values()->all() : null,
            'day_of_month' => $frequency === RoutineFrequency::Monthly ? (int) $this->dayOfMonth : null,
            'is_active' => $this->isActive,
            'steps' => collect($this->steps)
                ->map(fn (array $step): array => ['id' => $step['id'], 'name' => trim($step['name'])])
                ->filter(fn (array $step): bool => $step['name'] !== '')
                ->values()
                ->all(),
        ];
    }

    protected function validationError(): string
    {
        $name = trim($this->name);

        if ($name === '') {
            return 'Give the routine a name.';
        }

        if (mb_strlen($name) > 255) {
            return 'The name is too long (255 characters max).';
        }

        if ($this->frequency === RoutineFrequency::Weekly && $this->weekdays === []) {
            return 'Choose at least one day of the week.';
        }

        if ($this->frequency === RoutineFrequency::Monthly && (! ctype_digit(trim($this->dayOfMonth)) || (int) $this->dayOfMonth < 1 || (int) $this->dayOfMonth > 31)) {
            return 'Enter a day of the month between 1 and 31.';
        }

        if (collect($this->steps)->contains(fn (array $step): bool => mb_strlen(trim($step['name'])) > 255)) {
            return 'A step is too long (255 characters max).';
        }

        return '';
    }

    protected function saveRoutine(?int $id = null): void
    {
        if ($this->saving) {
            return;
        }

        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        if ($this->recordTeamId === null) {
            $this->error = 'Select a team on the dashboard. If none are listed, sync with Sunny first.';

            return;
        }

        if ($this->recordTeamId !== app(SunnyTeam::class)->current()?->id) {
            $this->error = 'The active team changed. Reopen this form from the dashboard before saving.';

            return;
        }

        $this->saving = true;

        try {
            app(SunnyOutbox::class)->queueRoutine($this->recordTeamId, $this->routinePayload(), $id);
            app(SunnySyncCoordinator::class)->dispatch();
            $this->back();
        } catch (ValidationException $exception) {
            $this->error = collect($exception->errors())->flatten()->first() ?? 'Check the form and try again.';
        } catch (Throwable $exception) {
            report($exception);
            $this->error = 'Unable to save on this phone. Try again.';
        } finally {
            $this->saving = false;
        }
    }
}
