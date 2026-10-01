<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ShowsQueuedChange;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Models\PendingWrite;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;

class RoutineDetail extends NativeComponent
{
    use ChecksSunnySync;
    use ShowsQueuedChange;

    public string $newStep = '';

    public string $error = '';

    /**
     * @return array{id: int, team_id: int, user_id: int|null, name: string, time_of_day: TimeOfDay, frequency: RoutineFrequency, weekdays: list<int>|null, day_of_month: int|null, is_active: bool, summary: string}|null
     */
    #[Computed]
    public function routine(): ?array
    {
        return AllRoutines::find((int) $this->param('id'));
    }

    /**
     * @return list<array{id: int, name: string, error: string|null}>
     */
    #[Computed]
    public function steps(): array
    {
        if ($this->routine === null) {
            return [];
        }

        $steps = collect(AllRoutines::stepsOf($this->routine['id']));
        $errors = PendingWrite::query()->forCurrentServer()->where('resource', 'routine_steps')
            ->whereIn('record_id', $steps->pluck('id'))->whereNotNull('error')->pluck('error', 'record_id');

        return $steps->map(fn (array $step): array => [
            'id' => $step['id'],
            'name' => $step['name'],
            'error' => $errors[$step['id']] ?? null,
        ])->all();
    }

    public function addStep(string $text = ''): void
    {
        $name = trim($text !== '' ? $text : $this->newStep);

        if ($this->routine === null || $name === '') {
            return;
        }

        if (mb_strlen($name) > 255) {
            $this->error = 'The step is too long (255 characters max).';

            return;
        }

        if ($this->change(fn (SunnyOutbox $outbox) => $outbox->queue('routine_steps', $this->routine['team_id'], ['routine_id' => $this->routine['id'], 'name' => $name]))) {
            $this->newStep = '';
        }
    }

    public function removeStep(int $id): void
    {
        if (collect($this->steps)->contains('id', $id)) {
            $this->change(fn (SunnyOutbox $outbox) => $outbox->delete('routine_steps', $this->routine['team_id'], $id));
        }
    }

    public function confirmDeleteRoutine(): void
    {
        if ($this->routine === null) {
            return;
        }

        Dialog::alert('Delete routine?', '“'.$this->routine['name'].'” will be deleted for everyone on your team.', [
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
        if ($this->routine !== null && $this->change(fn (SunnyOutbox $outbox) => $outbox->delete('routines', $this->routine['team_id'], $this->routine['id']))) {
            $this->back();
        }
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
        unset($this->routine, $this->steps, $this->queuedChange);
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
        return view('native.routine-detail');
    }

    private function change(callable $callback): bool
    {
        try {
            $callback(app(SunnyOutbox::class));
        } catch (ValidationException $exception) {
            $this->error = collect($exception->errors())->flatten()->first() ?? 'Unable to save on this phone. Try again.';

            return false;
        }

        app(SunnySyncCoordinator::class)->dispatch();
        $this->error = '';
        $this->refreshLocalSyncedData();

        return true;
    }
}
