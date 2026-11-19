<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class UpdateProfileRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    public function __construct(private readonly string $name, private readonly string $email) {}

    public function resolveEndpoint(): string
    {
        return '/user';
    }

    /**
     * @return array{name: string, email: string}
     */
    protected function defaultBody(): array
    {
        return ['name' => $this->name, 'email' => $this->email];
    }
}
