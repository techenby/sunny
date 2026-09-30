<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesListForm;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class CreateList extends NativeComponent
{
    use ChecksSunnySync;
    use ManagesListForm;

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

        $this->saveRecord('checklists', $this->listPayload());
    }

    public function render(): View
    {
        return view('native.create-list');
    }
}
