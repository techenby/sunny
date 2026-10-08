<?php

declare(strict_types=1);

namespace App\Livewire\Traits;

use App\Models\Team;
use App\Support\KioskTeam;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

trait WithKioskTeam
{
    #[Locked]
    public ?int $teamId = null;

    public function mountWithKioskTeam(): void
    {
        $this->teamId ??= KioskTeam::resolve()->id;
    }

    #[Computed]
    public function team(): Team
    {
        if (KioskTeam::inKioskSession()) {
            return KioskTeam::resolve();
        }

        $this->teamId ??= KioskTeam::resolve()->id;

        $team = Team::query()->findOrFail($this->teamId);

        abort_unless(Auth::user()->belongsToTeam($team), 403);

        return $team;
    }
}
