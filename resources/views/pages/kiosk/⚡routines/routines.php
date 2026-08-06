<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::kiosk')] class extends Component
{
    #[Computed]
    public function routines(): Collection
    {
        return Auth::user()->currentTeam
            ->routines()
            ->with(['tasks.completions'])
            ->orderBy('cadence')
            ->get();
    }

    public function toggle(int $taskId): void
    {

    }
};
