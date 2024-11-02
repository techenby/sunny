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
            'lat' => 41.61349933574811,
            'lon' => -88.20253686791719,
            'exclude' => 'minutely,hourly',
            'units' => 'imperial',
        ];
    }
}
