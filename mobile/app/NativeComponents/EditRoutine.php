<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesRoutineForm;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class EditRoutine extends NativeComponent
{
    use ChecksSunnySync;
    use ManagesRoutineForm;

    public function mount(): void
    {
        if ($this->routine !== null) {
            $this->fillFromRoutine($this->routine);
        }
    }

    /**
     * @return array{id: int, team_id: int, user_id: int|null, name: string, time_of_day: TimeOfDay, frequency: RoutineFrequency, weekdays: list<int>|null, day_of_month: int|null, is_active: bool, summary: string}|null
     */
    #[Computed]
    public function routine(): ?array
    {
        return Routines::find((int) $this->param('id'));
    }

    public function save(): void
    {
        if ($this->routine === null) {
            $this->error = 'This record could not be found.';

            return;
        }

        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        $this->saveRecord('routines', $this->routinePayload(), $this->routine['id']);
    }

    public function render(): View
    {
        return view('native.edit-routine');
    }
}
