<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesRoutineForm;
use App\Http\Integrations\Sunny\SunnyTeam;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class CreateRoutine extends NativeComponent
{
    use ChecksSunnySync;
    use ManagesRoutineForm;

    public function mount(): void
    {
        $this->recordTeamId = app(SunnyTeam::class)->current()?->id;
    }

    public function save(): void
    {
        $this->saveRoutine();
    }

    public function render(): View
    {
        return view('native.create-routine');
    }
}
