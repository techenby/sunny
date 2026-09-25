<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class CreateTokenRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $email,
        #[\SensitiveParameter] private readonly string $password,
        private readonly string $deviceName,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/sanctum/token';
    }

    /**
     * @return array{email: string, password: string, device_name: string}
     */
    protected function defaultBody(): array
    {
        return [
            'email' => $this->email,
            'password' => $this->password,
            'device_name' => $this->deviceName,
        ];
    }
}
