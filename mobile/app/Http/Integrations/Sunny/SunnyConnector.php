<?php

namespace App\Http\Integrations\Sunny;

use InvalidArgumentException;
use Saloon\Http\Connector;
use Saloon\Http\Response;
use Saloon\Traits\Plugins\AcceptsJson;
use Saloon\Traits\Plugins\AlwaysThrowOnErrors;

class SunnyConnector extends Connector
{
    use AcceptsJson;
    use AlwaysThrowOnErrors;

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $caBundle = null,
    ) {
        throw_if(
            ! filter_var($baseUrl, FILTER_VALIDATE_URL) || ! in_array(parse_url($baseUrl, PHP_URL_SCHEME), ['http', 'https'], true),
            InvalidArgumentException::class,
            'Set SUNNY_API_URL to the main application API URL, including /api.',
        );

        throw_if(
            $caBundle !== null && (! is_file($caBundle) || ! is_readable($caBundle)),
            InvalidArgumentException::class,
            'The configured Sunny development CA bundle must be a readable PEM file.',
        );
    }

    public function resolveBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    public function hasRequestFailed(Response $response): ?bool
    {
        return $response->status() >= 300;
    }

    /**
     * @return array<string, int|bool|string>
     */
    protected function defaultConfig(): array
    {
        return [
            'connect_timeout' => 10,
            'timeout' => 30,
            'allow_redirects' => false,
            'verify' => $this->caBundle ?? true,
        ];
    }
}
