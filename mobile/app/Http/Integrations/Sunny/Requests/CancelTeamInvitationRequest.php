<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class CancelTeamInvitationRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(private readonly string $teamSlug, private readonly string $code) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/invitations/'.rawurlencode($this->code);
    }
}
