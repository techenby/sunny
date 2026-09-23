<?php

namespace App\Http\Integrations\Sunny;

use InvalidArgumentException;
use Saloon\Http\Connector;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

class SunnyConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;

    public function __construct(private readonly string $baseUrl)
    {
        throw_if(
            ! filter_var($baseUrl, FILTER_VALIDATE_URL) || ! in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true),
            InvalidArgumentException::class,
            'Set SUNNY_API_URL to the main application API URL, including /api.',
        );
    }

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * @return array<string, int|bool>
     */
    protected function defaultConfig(): array
    {
        return [
            'connect_timeout' => 10,
            'timeout' => 30,
            'allow_redirects' => false,
        ];
    }
}
