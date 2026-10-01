<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesRoutineForm;
use App\Concerns\ShowsQueuedChange;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Models\Routine;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;

class EditRoutine extends NativeComponent
{
    use ChecksSunnySync;
    use ManagesRoutineForm;
    use ShowsQueuedChange;

    public function mount(): void
    {
        if ($this->routine !== null) {
            $this->fillFromRoutine($this->routine);
        }
    }

    #[Computed]
    public function routine(): ?Routine
    {
        return Routine::forActiveTeam()->with('steps')->find((int) $this->param('id'));
    }

    #[Computed]
    public function deleting(): bool
    {
        return $this->routine !== null && app(SunnyOutbox::class)->pendingRoutineDeletes()->contains($this->routine->id);
    }

    public function save(): void
    {
        if ($this->routine === null) {
            $this->error = 'This routine could not be found.';

            return;
        }

        $this->saveRoutine($this->routine->id);
    }

    public function confirmDelete(): void
    {
        if ($this->routine === null || $this->deleting) {
            return;
        }

        Dialog::alert('Delete routine?', $this->routine->id < 0
            ? 'This routine never reached Sunny, so it will be deleted from this phone.'
            : 'The routine and its steps will be deleted for everyone on this team.', [
                ['label' => 'Cancel', 'style' => 'cancel'],
                ['label' => 'Delete', 'style' => 'destructive'],
            ])->id('delete-routine')->show();
    }

    #[On(ButtonPressed::class)]
    public function onDiscardQueuedChangePressed(string $label, ?string $id = null): void
    {
        if ($id === 'discard-queued-change' && $label === 'Discard') {
            $this->discardQueuedChange();
        }

        if ($id === 'delete-routine' && $label === 'Delete') {
            $this->deleteRoutine();
        }
    }

    protected function deleteRoutine(): void
    {
        if ($this->routine === null) {
            return;
        }

        try {
            app(SunnyOutbox::class)->queueRoutineDelete($this->routine->id);
        } catch (ValidationException $exception) {
            Dialog::toast(collect($exception->errors())->flatten()->first());

            return;
        }

        app(SunnySyncCoordinator::class)->dispatch();
        $this->back();
    }

    public function render(): View
    {
        return view('native.edit-routine');
    }

    protected function queuedResource(): string
    {
        return 'routines';
    }

    protected function queuedRecordId(): ?int
    {
        return $this->routine?->id;
    }

    protected function refreshLocalSyncedData(): void
    {
        unset($this->routine, $this->queuedChange, $this->deleting);
    }
}
