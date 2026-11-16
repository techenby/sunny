<?php

use App\Actions\Routines\GenerateRoutineOccurrences;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public const LIMIT = 3;

    /** @return Collection<int, RoutineOccurrence> */
    #[Computed]
    public function occurrences(): Collection
    {
        return $this->todaysOccurrences->take(self::LIMIT);
    }

    #[Computed]
    public function total(): int
    {
        return $this->todaysOccurrences->count();
    }

    /** @return Collection<int, RoutineOccurrence> */
    #[Computed]
    public function todaysOccurrences(): Collection
    {
        return resolve(GenerateRoutineOccurrences::class)
            ->forDate(Auth::user()->currentTeam)
            ->filter(fn (RoutineOccurrence $occurrence): bool => in_array($occurrence->routine->user_id, [null, Auth::id()], true))
            ->values();
    }

    public function toggle(int $occurrenceStepId): void
    {
        $occurrenceStep = RoutineOccurrenceStep::with('occurrence.routine')->findOrFail($occurrenceStepId);

        $this->authorize('complete', $occurrenceStep->occurrence->routine);

        $occurrenceStep->toggle(Auth::user());

        unset($this->occurrences, $this->total, $this->todaysOccurrences);
    }
};
