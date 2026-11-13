<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class DeleteAccountRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::DELETE;

    public function __construct(#[\SensitiveParameter] private readonly string $password) {}

    public function resolveEndpoint(): string
    {
        return '/user';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return ['password' => $this->password];
    }
}
