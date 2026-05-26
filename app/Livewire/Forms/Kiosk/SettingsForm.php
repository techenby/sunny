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

    #[Validate([
        'address' => 'required|array',
        'address.*' => 'required',
    ])]
    public array $address = [
        'address' => '',
        'city' => '',
        'state' => '',
        'zip' => '',
        'lat' => '',
        'long' => '',
    ];

    public function load(Team $team)
    {
        $this->editingTeam = $team;
        $this->timezone = $team->timezone;
        $this->week_start = $team->week_start;
        $this->address = $team->address ?? $this->address;
    }

    public function save()
    {
        $data = $this->validate();

        $this->editingTeam->update($data);
    }
}
