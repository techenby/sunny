<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class UpdateTeamRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    /** @param  array{name?: string, timezone?: string, week_start?: int}  $attributes */
    public function __construct(private readonly string $teamSlug, private readonly array $attributes) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug);
    }

    /**
     * @return array{name?: string, timezone?: string, week_start?: int}
     */
    protected function defaultBody(): array
    {
        return $this->attributes;
    }
}
