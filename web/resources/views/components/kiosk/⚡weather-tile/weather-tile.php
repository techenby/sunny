<?php

use App\Actions\Kiosk\FetchWeather;
use App\Models\Team;
use Livewire\Component;

new class extends Component
{
    public ?string $location = null;

    public ?float $temp = null;

    public ?float $high = null;

    public ?float $low = null;

    public ?string $description = null;

    public ?string $icon = null;

    public function mount(Team $team): void
    {
        $weather = resolve(FetchWeather::class)->handle($team);

        if ($weather === null) {
            return;
        }

        $this->location = $weather['location'];
        $this->temp = $weather['temp'];
        $this->high = $weather['high'];
        $this->low = $weather['low'];
        $this->description = $weather['description'];
        $this->icon = $weather['icon'];
    }
};
