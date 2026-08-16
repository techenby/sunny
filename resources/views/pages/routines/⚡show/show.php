<?php

use App\Models\Routine;
use App\Models\RoutineStep;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Routine')] class extends Component
{
    public Routine $routine;

    public string $newStep = '';

    /** @var array<int, string> */
    public array $names = [];

    public function mount(): void
    {
        $this->syncNames();
    }

    /** @return Collection<int, RoutineStep> */
    #[Computed]
    public function steps()
    {
        return $this->routine->steps()->get();
    }

    public function addStep(): void
    {
        $this->authorize('update', $this->routine);

        $this->newStep = trim($this->newStep);

        if ($this->newStep === '') {
            return;
        }

        $this->validate(['newStep' => ['string', 'max:255']]);

        $this->routine->steps()->create(['name' => $this->newStep]);
        $this->newStep = '';

        unset($this->steps);
        $this->syncNames();
    }

    public function rename(int $stepId): void
    {
        $this->authorize('update', $this->routine);

        $step = $this->step($stepId);
        $this->names[$stepId] = trim($this->names[$stepId] ?? '');

        if ($this->names[$stepId] === '') {
            $this->names[$stepId] = $step->name;

            return;
        }

        $this->validate(["names.{$stepId}" => ['string', 'max:255']]);

        $step->update(['name' => $this->names[$stepId]]);

        unset($this->steps);
    }

    public function removeStep(int $stepId): void
    {
        $this->authorize('update', $this->routine);

        $this->step($stepId)->delete();

        unset($this->steps);
        $this->syncNames();
    }

    public function moveDown(int $stepId): void
    {
        $this->swap($stepId, 1);
    }

    public function moveUp(int $stepId): void
    {
        $this->swap($stepId, -1);
    }

    private function step(int $stepId): RoutineStep
    {
        return $this->routine->steps()->findOrFail($stepId);
    }

    private function swap(int $stepId, int $direction): void
    {
        $this->authorize('update', $this->routine);

        $steps = $this->steps->values();
        $index = $steps->search(fn (RoutineStep $step): bool => $step->id === $stepId);
        $target = $steps->get($index + $direction);

        if ($index === false || ! $target instanceof RoutineStep) {
            return;
        }

        $step = $steps->get($index);

        [$step->position, $target->position] = [$target->position, $step->position];

        $step->save();
        $target->save();

        unset($this->steps);
    }

    private function syncNames(): void
    {
        $this->names = $this->steps->pluck('name', 'id')->all();
    }
};
