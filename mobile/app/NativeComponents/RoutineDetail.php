<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Concerns\ShowsQueuedChange;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Models\PendingWrite;
use App\Models\RoutineStep;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;

class RoutineDetail extends Component
{
    use ChecksSunnySync;
    use ShowsQueuedChange;

    public string $newStep = '';

    public string $error = '';

    public ?int $editingStepId = null;

    public string $editingName = '';

    public bool $reordering = false;

    /** @var list<int> */
    public array $stepOrder = [];

    public ?int $movedStepId = null;

    /**
     * @return array{id: int, team_id: int, user_id: int|null, name: string, time_of_day: TimeOfDay, frequency: RoutineFrequency, weekdays: list<int>|null, day_of_month: int|null, is_active: bool, summary: string}|null
     */
    #[Computed]
    public function routine(): ?array
    {
        return Routines::find((int) $this->param('id'));
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

        $steps = collect(Routines::stepsOf($this->routine['id']));
        $errors = PendingWrite::query()->forCurrentServer()->where('resource', 'routine_steps')
            ->whereIn('record_id', $steps->pluck('id'))->whereNotNull('error')->pluck('error', 'record_id');

        if ($this->reordering) {
            $order = array_flip($this->stepOrder);
            $steps = $steps->sortBy(fn (array $step, int $index): array => [$order[$step['id']] ?? count($order), $index]);
        }

        return $steps->map(fn (array $step): array => [
            'id' => $step['id'],
            'name' => $step['name'],
            'error' => $errors[$step['id']] ?? null,
        ])->values()->all();
    }

    public function addStep(string $text = ''): void
    {
        $name = trim($text !== '' ? $text : $this->newStep);

        if ($this->routine === null || $name === '') {
            return;
        }

        if ($this->saveStep(['routine_id' => $this->routine['id'], 'name' => $name])) {
            $this->newStep = '';
        }
    }

    public function editStep(int $id): void
    {
        $step = collect($this->steps)->firstWhere('id', $id);

        if ($step !== null) {
            $this->editingStepId = $id;
            $this->editingName = $step['name'];
        }
    }

    public function renameStep(string $text = ''): void
    {
        $name = trim($text !== '' ? $text : $this->editingName);
        $step = collect($this->steps)->firstWhere('id', $this->editingStepId);

        if ($step === null || $name === '' || $name === $step['name'] || $this->saveStep(['name' => $name], $step['id'])) {
            $this->cancelEdit();
        }
    }

    public function cancelEdit(): void
    {
        $this->editingStepId = null;
        $this->editingName = '';
    }

    public function removeStep(int $id): void
    {
        if (collect($this->steps)->contains('id', $id)) {
            $this->change(fn (SunnyOutbox $outbox) => $outbox->delete('routine_steps', $this->routine['team_id'], $id));
        }
    }

    public function startReordering(): void
    {
        if (count($this->steps) < 2) {
            return;
        }

        $this->cancelEdit();
        $this->stepOrder = array_column($this->steps, 'id');
        $this->movedStepId = null;
        $this->reordering = true;
        unset($this->steps);
    }

    public function moveStepUp(int $id): void
    {
        $this->moveStep($id, -1);
    }

    public function moveStepDown(int $id): void
    {
        $this->moveStep($id, 1);
    }

    public function finishReordering(): void
    {
        $order = array_column($this->steps, 'id');
        $this->stopReordering();

        if ($this->routine === null || $order === array_column($this->steps, 'id')) {
            return;
        }

        $this->change(function (SunnyOutbox $outbox) use ($order): void {
            $outbox->queue('routines', $this->routine['team_id'], ['step_order' => $order], $this->routine['id']);

            foreach ($order as $index => $id) {
                RoutineStep::query()->whereKey($id)->update(['position' => $index + 1]);
            }
        });
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

    /** @param array{routine_id?: int, name: string} $payload */
    private function saveStep(array $payload, ?int $id = null): bool
    {
        return $this->change(function (SunnyOutbox $outbox) use ($payload, $id) {
            Validator::make(['step' => $payload['name']], ['step' => ['string', 'max:255']], [
                'step.max' => 'The step is too long (255 characters max).',
            ])->validate();

            $outbox->queue('routine_steps', $this->routine['team_id'], $payload, $id);
        });
    }

    private function moveStep(int $id, int $offset): void
    {
        $order = array_column($this->steps, 'id');
        $from = array_search($id, $order, true);

        if (! $this->reordering || $from === false || ! isset($order[$from + $offset])) {
            return;
        }

        [$order[$from], $order[$from + $offset]] = [$order[$from + $offset], $order[$from]];
        $this->stepOrder = $order;
        $this->movedStepId = $id;
        unset($this->steps);
    }

    private function stopReordering(): void
    {
        $this->reordering = false;
        $this->stepOrder = [];
        $this->movedStepId = null;
        unset($this->steps);
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
