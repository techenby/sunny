<?php

use App\Actions\Routines\GenerateRoutineOccurrences;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::kiosk')] class extends Component
{
    #[Url]
    public string $focusedDate = '';

    public function mount(): void
    {
        $this->focusedDate = $this->team()->today()->toDateString();
    }

    /**
     * One column per member with routines today, then the household column for
     * anything nobody has claimed.
     *
     * @return array<int, array{
     *     key: string,
     *     name: string,
     *     occurrences: Collection<int, RoutineOccurrence>,
     *     completed: int,
     *     total: int
     * }>
     */
    #[Computed]
    public function columns(): array
    {
        [$household, $assigned] = $this->occurrences()
            ->partition(fn (RoutineOccurrence $occurrence): bool => $occurrence->routine->user === null);

        $columns = $assigned
            ->groupBy(fn (RoutineOccurrence $occurrence): int => $occurrence->routine->user->id)
            ->map(fn (Collection $occurrences): array => $this->column(
                'user-' . $occurrences->first()->routine->user->id,
                $occurrences->first()->routine->user->name,
                $occurrences,
            ))
            ->sortBy('name')
            ->values();

        if ($household->isNotEmpty()) {
            $columns->push($this->column('household', __('Household'), $household));
        }

        return $columns->all();
    }

    #[Computed]
    public function isToday(): bool
    {
        return $this->date()->isSameDay($this->team()->today());
    }

    #[Computed]
    public function heading(): string
    {
        return $this->isToday()
            ? __('Today')
            : $this->date()->format('l, F j');
    }

    public function previous(): void
    {
        $this->focusedDate = $this->date()->subDay()->toDateString();

        unset($this->columns, $this->isToday, $this->heading);
    }

    public function next(): void
    {
        $this->focusedDate = $this->date()->addDay()->toDateString();

        unset($this->columns, $this->isToday, $this->heading);
    }

    public function current(): void
    {
        $this->focusedDate = $this->team()->today()->toDateString();

        unset($this->columns, $this->isToday, $this->heading);
    }

    public function toggle(int $occurrenceStepId): void
    {
        $occurrenceStep = RoutineOccurrenceStep::with('occurrence.routine')->findOrFail($occurrenceStepId);

        $this->authorize('complete', $occurrenceStep->occurrence->routine);

        $occurrenceStep->toggle(Auth::user());

        unset($this->columns);
    }

    /**
     * @param Collection<int, RoutineOccurrence> $occurrences
     *
     * @return array{
     *     key: string,
     *     name: string,
     *     occurrences: Collection<int, RoutineOccurrence>,
     *     completed: int,
     *     total: int
     * }
     */
    private function column(string $key, string $name, Collection $occurrences): array
    {
        $steps = $occurrences->flatMap(fn (RoutineOccurrence $occurrence) => $occurrence->steps);

        return [
            'key' => $key,
            'name' => $name,
            'occurrences' => $occurrences,
            'completed' => $steps->filter(fn (RoutineOccurrenceStep $step): bool => $step->isCompleted())->count(),
            'total' => $steps->count(),
        ];
    }

    /** @return Collection<int, RoutineOccurrence> */
    private function occurrences(): Collection
    {
        return resolve(GenerateRoutineOccurrences::class)->forDate($this->team(), $this->date());
    }

    private function date(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->focusedDate, $this->team()->timezone)->startOfDay();
    }

    private function team(): Team
    {
        return Auth::user()->currentTeam;
    }
};
