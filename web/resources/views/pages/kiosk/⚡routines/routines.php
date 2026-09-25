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
     * One column per routine due on the focused date, ordered by time of day.
     *
     * @return array<int, array{
     *     key: string,
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
     * @return array{
     *     key: string,
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
