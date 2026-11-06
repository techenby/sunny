<?php

use App\Actions\MealPlanning\ResolveMealPlan;
use App\Livewire\Traits\WithKioskTeam;
use App\Models\MealSlot;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::kiosk')] class extends Component
{
    use WithKioskTeam;

    #[Computed]
    public function days(): Collection
    {
        return (new ResolveMealPlan)->handle($this->team, ...ResolveMealPlan::week($this->team, $this->team->today()));
    }

    /** @return Collection<int, MealSlot> */
    #[Computed]
    public function mealSlots(): Collection
    {
        return $this->days->flatMap(fn (array $day) => $day['slots']->pluck('slot'))->unique('id')->sortBy('position')->values();
    }
};
