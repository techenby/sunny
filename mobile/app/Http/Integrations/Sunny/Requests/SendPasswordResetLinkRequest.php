<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class SendPasswordResetLinkRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $email) {}

    public function resolveEndpoint(): string
    {
        return '/forgot-password';
    }

    /**
     * @return array{email: string}
     */
    protected function defaultBody(): array
    {
        return ['email' => $this->email];
    }

    /**
     * @return array{connect_timeout: int, timeout: int}
     */
    protected function defaultConfig(): array
    {
        return ['connect_timeout' => 4, 'timeout' => 15];
    }
}
