<?php

namespace App\Concerns;

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Native\Mobile\Attributes\Computed;

trait ManagesRoutineForm
{
    use SavesSunnyRecord;

    public string $name = '';

    public int $timeOfDayIndex = 0;

    public int $frequencyIndex = 0;

    /** @var list<int> */
    public array $weekdays = [];

    public string $dayOfMonth = '';

    public bool $isActive = true;

    public string $error = '';

    /**
     * @param  array{team_id: int, name: string, time_of_day: TimeOfDay, frequency: RoutineFrequency, weekdays: list<int>|null, day_of_month: int|null, is_active: bool}  $routine
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
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function timeOfDayOptions(): array
    {
        return array_map(fn (TimeOfDay $timeOfDay): string => $timeOfDay->label(), TimeOfDay::cases());
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function frequencyOptions(): array
    {
        return array_map(fn (RoutineFrequency $frequency): string => $frequency->label(), RoutineFrequency::cases());
    }

    /**
     * @return list<array<int, string>>
     */
    #[Computed]
    public function weekdayRows(): array
    {
        $days = array_map(fn (string $day): string => Str::substr($day, 0, 3), CarbonImmutable::getDays());

        return array_chunk($days, 4, preserve_keys: true);
    }

    public function toggleWeekday(int $day, bool $selected): void
    {
        $weekdays = array_diff($this->weekdays, [$day]);

        if ($selected) {
            $weekdays[] = $day;
        }

        sort($weekdays);
        $this->weekdays = array_values($weekdays);
    }

    protected function frequency(): RoutineFrequency
    {
        return RoutineFrequency::cases()[$this->frequencyIndex] ?? RoutineFrequency::Daily;
    }

    protected function routinePayload(): array
    {
        $frequency = $this->frequency();

        return [
            'name' => trim($this->name),
            'time_of_day' => (TimeOfDay::cases()[$this->timeOfDayIndex] ?? TimeOfDay::Morning)->value,
            'frequency' => $frequency->value,
            'weekdays' => $frequency === RoutineFrequency::Weekly ? $this->weekdays : null,
            'day_of_month' => $frequency === RoutineFrequency::Monthly ? (int) trim($this->dayOfMonth) : null,
            'is_active' => $this->isActive,
        ];
    }

    protected function validationError(): string
    {
        if (trim($this->name) === '') {
            return 'Give the routine a name.';
        }

        if (mb_strlen(trim($this->name)) > 255) {
            return 'The name is too long (255 characters max).';
        }

        if ($this->frequency() === RoutineFrequency::Weekly && $this->weekdays === []) {
            return 'Choose at least one day.';
        }

        if ($this->frequency() === RoutineFrequency::Monthly && ! in_array(trim($this->dayOfMonth), array_map(strval(...), range(1, 31)), strict: true)) {
            return 'Enter a day of the month from 1 to 31.';
        }

        return '';
    }
}
