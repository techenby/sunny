<?php

namespace App\Http\Integrations\OpenWeather;

use Saloon\Http\Auth\QueryAuthenticator;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;

class OpenWeather extends Connector
{
    use AcceptsJson;

    public function resolveBaseUrl(): string
    {
        return 'https://api.openweathermap.org/data/3.0';
    }

    protected function defaultAuth(): QueryAuthenticator
    {
        return new QueryAuthenticator('appid', config('services.openweather.key'));
    }
}
