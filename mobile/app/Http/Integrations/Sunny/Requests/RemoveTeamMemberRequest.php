<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class RemoveTeamMemberRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(private readonly string $teamSlug, private readonly int $memberId) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/members/'.$this->memberId;
    }
}
