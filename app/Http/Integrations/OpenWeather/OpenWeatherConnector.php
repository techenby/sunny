<?php

declare(strict_types=1);

namespace App\Http\Integrations\OpenWeather;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class OpenWeatherConnector extends Connector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return 'https://api.openweathermap.org/data/3.0/';
    }

    protected function defaultQuery(): array
    {
        return [
            'appid' => config('services.openweather.key'),
        ];
    }
}
