<?php

use App\Actions\Routines\GenerateRoutineOccurrences;
use App\Livewire\Traits\WithKioskTeam;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::kiosk')] class extends Component
{
    use WithKioskTeam;

    #[Url]
    public string $focusedDate = '';

    public function mount(): void
    {
        $this->focusedDate = $this->team->today()->toDateString();
    }

    /**
     * One column per routine due on the focused date, ordered by time of day.
     *
     * @return array<int, array{
     *     key: string,
     *     state: string,
     *     name: string,
     *     assignee: string,
     *     isHousehold: bool,
     *     icon: string,
     *     timeOfDay: string,
     *     steps: Collection<int, RoutineOccurrenceStep>,
     *     completed: int,
     *     total: int
     * }>
     */
    #[Computed]
    public function columns(): array
    {
        return $this->occurrences()
            ->map(fn (RoutineOccurrence $occurrence): array => $this->column($occurrence))
            ->all();
    }

    #[Computed]
    public function isToday(): bool
    {
        return $this->date()->isSameDay($this->team->today());
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
        $this->focusedDate = $this->team->today()->toDateString();

        unset($this->columns, $this->isToday, $this->heading);
    }

    #[Renderless]
    public function setStepCompleted(int $occurrenceStepId, bool $completed): void
    {
        $occurrenceStep = RoutineOccurrenceStep::query()
            ->with('occurrence.routine')
            ->whereHas('occurrence.routine', fn (Builder $query) => $query->whereBelongsTo($this->team))
            ->findOrFail($occurrenceStepId);

        $this->authorize('complete', $occurrenceStep->occurrence->routine);

        if ($occurrenceStep->isCompleted() === $completed) {
            return;
        }

        $completed ? $occurrenceStep->complete(Auth::user()) : $occurrenceStep->uncomplete();
    }

    /**
     * @return array{
     *     key: string,
     *     state: string,
     *     name: string,
     *     assignee: string,
     *     isHousehold: bool,
     *     icon: string,
     *     timeOfDay: string,
     *     steps: Collection<int, RoutineOccurrenceStep>,
     *     completed: int,
     *     total: int
     * }
     */
    private function column(RoutineOccurrence $occurrence): array
    {
        $routine = $occurrence->routine;
        $steps = $occurrence->steps;

        return [
            'key' => 'occurrence-' . $occurrence->id,
            'state' => md5($steps->map(fn (RoutineOccurrenceStep $step): string => $step->completed_at . '|' . $step->completed_by)->join(',')),
            'name' => $routine->name,
            'assignee' => $routine->user?->name ?? __('Household'),
            'isHousehold' => $routine->user === null,
            'icon' => $routine->time_of_day->getIcon(),
            'timeOfDay' => $routine->time_of_day->getLabel(),
            'steps' => $steps,
            'completed' => $steps->filter(fn (RoutineOccurrenceStep $step): bool => $step->isCompleted())->count(),
            'total' => $steps->count(),
        ];
    }

    /** @return Collection<int, RoutineOccurrence> */
    private function occurrences(): Collection
    {
        return resolve(GenerateRoutineOccurrences::class)->forDate($this->team, $this->date());
    }

    private function date(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->focusedDate, $this->team->timezone)->startOfDay();
    }
};
