<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Kiosk;

use App\Models\Team;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Validate;
use Livewire\Form;

class SettingsForm extends Form
{
    public Team $editingTeam;

    #[Validate('required|string|timezone:all')]
    public string $timezone = 'America/Chicago';

    #[Validate('required|int|between:0,6')]
    public int $week_start = Carbon::SUNDAY;

    public function load(Team $team)
    {
        $this->editingTeam = $team;
        $this->timezone = $team->timezone;
        $this->week_start = $team->week_start;
    }

    public function save()
    {
        $data = $this->validate();

        $this->editingTeam->query()->update($data);
    }
}
