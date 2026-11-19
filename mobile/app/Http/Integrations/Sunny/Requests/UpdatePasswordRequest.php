<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class UpdatePasswordRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PUT;

    public function __construct(
        #[\SensitiveParameter] private readonly string $currentPassword,
        #[\SensitiveParameter] private readonly string $password,
        #[\SensitiveParameter] private readonly string $passwordConfirmation,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/user/password';
    }

    /**
     * @return array{current_password: string, password: string, password_confirmation: string}
     */
    protected function defaultBody(): array
    {
        return [
            'current_password' => $this->currentPassword,
            'password' => $this->password,
            'password_confirmation' => $this->passwordConfirmation,
        ];
    }
}
