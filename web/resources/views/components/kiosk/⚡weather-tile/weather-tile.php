<?php

use App\Actions\Kiosk\FetchWeather;
use App\Models\Team;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Team $team;

    /**
     * @return array{
     *     location: string|null,
     *     temp: float,
     *     high: float,
     *     low: float,
     *     description: string|null,
     *     icon: string|null
     * }|null
     */
    #[Computed]
    public function weather(): ?array
    {
        return resolve(FetchWeather::class)->handle($this->team);
    }
};
