<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class CreateTeamRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $name) {}

    public function resolveEndpoint(): string
    {
        return '/teams';
    }

    /**
     * @return array{name: string}
     */
    protected function defaultBody(): array
    {
        return ['name' => $this->name];
    }
}
