<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ManagesRoutineForm;
use App\Concerns\ShowsQueuedChange;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
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

    /**
     * @return array{id: int, team_id: int, user_id: int|null, assignee: string|null, name: string, time_of_day: TimeOfDay, frequency: RoutineFrequency, weekdays: list<int>|null, day_of_month: int|null, is_active: bool, steps: list<array{id?: int, name: string}>|null}|null
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

    public function confirmDeleteRoutine(): void
    {
        if ($this->routine === null) {
            return;
        }

        Dialog::alert('Delete routine?', '“'.$this->routine['name'].'” and its steps will be deleted for everyone on your team.', [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Delete', 'style' => 'destructive'],
        ])->id('delete-routine')->show();
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id === 'delete-routine' && $label === 'Delete') {
            $this->deleteRoutine();
        } elseif ($id === 'discard-queued-change' && $label === 'Discard') {
            $this->discardQueuedChange();
        }
    }

    public function deleteRoutine(): void
    {
        if ($this->routine === null) {
            return;
        }

        try {
            app(SunnyOutbox::class)->delete('routines', $this->routine['team_id'], $this->routine['id']);
        } catch (ValidationException $exception) {
            $this->error = collect($exception->errors())->flatten()->first() ?? 'Unable to delete on this phone. Try again.';

            return;
        }

        app(SunnySyncCoordinator::class)->dispatch();
        $this->back();
    }

    #[On('sunny-sync-complete')]
    public function onSyncComplete(string $status): void
    {
        if ($status === 'finished') {
            $this->refreshLocalSyncedData();
        }
    }

    protected function refreshLocalSyncedData(): void
    {
        unset($this->routine, $this->queuedChange);
    }

    protected function queuedResource(): string
    {
        return 'routines';
    }

    protected function queuedRecordId(): ?int
    {
        return $this->routine['id'] ?? null;
    }

    public function render(): View
    {
        return view('native.edit-routine');
    }
}
