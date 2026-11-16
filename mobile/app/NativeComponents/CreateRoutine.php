<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesRoutineForm;
use Illuminate\View\View;

class CreateRoutine extends Component
{
    use ChecksSunnySync;
    use ManagesRoutineForm;

    public function mount(): void
    {
        $this->initializeTeam();
    }

    public function save(): void
    {
        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        $this->saveRecord('routines', $this->routinePayload());
    }

    public function render(): View
    {
        return view('native.create-routine');
    }
}
