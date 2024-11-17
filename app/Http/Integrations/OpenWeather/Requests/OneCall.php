<?php

namespace App\Http\Integrations\OpenWeather\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class OneCall extends Request
{
    protected Method $method = Method::GET;

    public function resolveEndpoint(): string
    {
        return '/onecall';
    }

    protected function defaultQuery(): array
    {
        return [
            'lat' => config('dashboard.tiles.weather.lat'),
            'lon' => config('dashboard.tiles.weather.lon'),
            'exclude' => 'minutely,hourly',
            'units' => 'imperial',
        ];
    }
}
