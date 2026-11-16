<?php

use App\Livewire\Forms\Routines\RoutineForm;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\User;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Routine $routine;

    public RoutineForm $form;

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

    /** @return Collection<int, User> */
    #[Computed]
    public function members(): Collection
    {
        return $this->routine->team->members()->orderBy('name')->get();
    }

    #[Computed]
    public function lastDone(): string
    {
        $dueOn = $this->routine->occurrences()
            ->whereHas('steps')
            ->whereDoesntHave('steps', fn (Builder $query) => $query->whereNull('completed_at'))
            ->latest('due_on')
            ->value('due_on');

        if ($dueOn === null) {
            return __('Not yet');
        }

        $dueOn = CarbonImmutable::parse($dueOn);
        $today = $this->routine->team->today();

        return match (true) {
            $dueOn->isSameDay($today) => __('Today'),
            $dueOn->isSameDay($today->subDay()) => __('Yesterday'),
            $dueOn->isSameYear($today) => $dueOn->format('l, F j'),
            default => $dueOn->format('F j, Y'),
        };
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

    public function delete(): void
    {
        $this->authorize('delete', $this->routine);

        $this->routine->delete();

        $this->redirect(route('routines.index'), navigate: true);
    }

    public function edit(): void
    {
        $this->authorize('update', $this->routine);

        $this->form->load($this->routine);

        $this->modal('routine-form')->show();
    }

    public function save(): void
    {
        $this->authorize('update', $this->routine);

        $this->form->save();

        $this->modal('routine-form')->close();
        Flux::toast(variant: 'success', text: __('Routine saved.'));

        $this->routine->refresh();
    }

    public function toggleActive(): void
    {
        $this->authorize('update', $this->routine);

        $this->routine->update(['is_active' => ! $this->routine->is_active]);
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
