<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Kiosk;

use App\Enums\Appearance;
use App\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rules\Enum;
use Livewire\Attributes\Validate;
use Livewire\Form;

class SettingsForm extends Form
{
    public Team $editingTeam;

    #[Validate('required|string|timezone:all')]
    public string $timezone = 'America/Chicago';

    #[Validate('required|int|between:0,6')]
    public int $week_start = Carbon::SUNDAY;

    #[Validate(['required', new Enum(Appearance::class)])]
    public string $appearance = Appearance::Dark->value;

    #[Validate('required|int|in:0,90,180,270')]
    public int $rotation = 0;

    #[Validate('required|int|in:0,1,2,5,10,15,30')]
    public int $screensaver_after = 5;

    #[Validate('required|int|in:0,5,10,15,30,60')]
    public int $return_home_after = 10;

    public bool $night_mode = false;

    #[Validate('exclude_unless:night_mode,true|required|date_format:H:i')]
    public ?string $night_starts_at = '22:00';

    #[Validate('exclude_unless:night_mode,true|required|date_format:H:i|different:night_starts_at')]
    public ?string $night_ends_at = '06:00';

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
        $this->appearance = $team->appearance->value;
        $this->rotation = $team->rotation;
        $this->screensaver_after = $team->screensaver_after;
        $this->return_home_after = $team->return_home_after;
        $this->night_mode = $team->night_starts_at !== null;
        $this->night_starts_at = $team->night_starts_at ?? $this->night_starts_at;
        $this->night_ends_at = $team->night_ends_at ?? $this->night_ends_at;
        $this->address = $team->address ?? $this->address;
    }

    public function save()
    {
        $data = $this->validate();

        $this->editingTeam->update([
            ...$data,
            'night_starts_at' => $this->night_mode ? $data['night_starts_at'] : null,
            'night_ends_at' => $this->night_mode ? $data['night_ends_at'] : null,
        ]);
    }
}
