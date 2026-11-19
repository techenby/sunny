<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class RegisterRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $name,
        private readonly string $email,
        #[\SensitiveParameter] private readonly string $password,
        #[\SensitiveParameter] private readonly string $passwordConfirmation,
        private readonly string $deviceName,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/register';
    }

    /**
     * @return array{name: string, email: string, password: string, password_confirmation: string, device_name: string}
     */
    protected function defaultBody(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->passwordConfirmation,
            'device_name' => $this->deviceName,
        ];
    }

    /**
     * @return array{connect_timeout: int, timeout: int}
     */
    protected function defaultConfig(): array
    {
        return ['connect_timeout' => 4, 'timeout' => 15];
    }
}
