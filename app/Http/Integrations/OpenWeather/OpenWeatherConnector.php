<?php

namespace App\Http\Integrations\OpenWeather;

use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class OpenWeatherConnector extends Connector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return 'https://api.openweathermap.org';
    }

    protected function defaultQuery(): array
    {
        return [
            'appid' => config('services.openweather.key'),
        ];
    }
}
