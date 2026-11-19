<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesListForm;
use Illuminate\View\View;

class CreateList extends Component
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

        $this->saveRecord('checklists', [...$this->listPayload(), ...$this->ownerPayload(creating: true)]);
    }

    public function render(): View
    {
        return view('native.create-list');
    }
}
