<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesRoutineForm;
use App\Layouts\HidesTabBar;
use Illuminate\View\View;

class CreateRoutine extends Component implements HidesTabBar
{
    use ChecksSunnySync;
    use ManagesRoutineForm;

    public function mount(): void
    {
        $this->initializeTeam();
        $this->startsOn = $this->teamToday()->toDateString();
    }

    public function save(): void
    {
        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        $this->saveRecord('routines', [...$this->routinePayload(), ...$this->ownerPayload(creating: true)]);
    }

    public function render(): View
    {
        return view('native.create-routine');
    }
}
