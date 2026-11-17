<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class UpdateTeamMemberRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    public function __construct(private readonly string $teamSlug, private readonly int $memberId, private readonly string $role) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/members/'.$this->memberId;
    }

    /**
     * @return array{role: string}
     */
    protected function defaultBody(): array
    {
        return ['role' => $this->role];
    }
}
