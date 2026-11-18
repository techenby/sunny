<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetSharedRecipeRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $token) {}

    public function resolveEndpoint(): string
    {
        return '/shared-recipes/'.rawurlencode($this->token);
    }

    /**
     * @return array{connect_timeout: int, timeout: int}
     */
    protected function defaultConfig(): array
    {
        return ['connect_timeout' => 4, 'timeout' => 8];
    }
}
