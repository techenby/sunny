<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetTeamInvitationsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly string $teamSlug) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/invitations';
    }
}
