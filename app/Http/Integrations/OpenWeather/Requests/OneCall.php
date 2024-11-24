<?php

namespace App\Http\Integrations\OpenWeather\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class OneCall extends Request
{
    protected Method $method = Method::GET;

    public function __construct(protected readonly string $lat, protected readonly string $lon) {
        //
    }

    public function resolveEndpoint(): string
    {
        return '/onecall';
    }

    protected function defaultQuery(): array
    {
        return [
            'lat' => $this->lat,
            'lon' => $this->lon,
            'exclude' => 'minutely,hourly',
            'units' => 'imperial',
        ];
    }
}
