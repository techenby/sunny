<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesListForm;
use App\Enums\ChecklistType;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class EditList extends NativeComponent
{
    use ChecksSunnySync;
    use ManagesListForm;

    public function mount(): void
    {
        if ($this->checklist !== null) {
            $this->fillFromList($this->checklist);
        }
    }

    /**
     * @return array{id: int, team_id: int, user_id: int|null, type: ChecklistType, name: string, created_at: string, updated_at: string}|null
     */
    #[Computed]
    public function checklist(): ?array
    {
        return Lists::find((int) $this->param('id'));
    }

    public function save(): void
    {
        if ($this->checklist === null) {
            $this->error = 'This record could not be found.';

            return;
        }

        $this->error = $this->validationError();

        if ($this->error !== '') {
            return;
        }

        $this->saveRecord('checklists', $this->listPayload(), $this->checklist['id']);
    }

    public function render(): View
    {
        return view('native.edit-list');
    }
}
