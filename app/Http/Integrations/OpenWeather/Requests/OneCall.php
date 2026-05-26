<?php

namespace App\Http\Integrations\OpenWeather\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class OneCall extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        public readonly float $lat,
        public readonly float $lon,
        public readonly ?string $exclude = null
    ) {}

    public function resolveEndpoint(): string
    {
        return '/onecall';
    }

    public function defaultQuery(): array
    {
        return array_filter([
            'lat' => $this->lat,
            'lon' => $this->lon,
            'exclude' => $this->exclude,
        ]);
    }
}
