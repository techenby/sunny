<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class LeaveTeamRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(private readonly string $teamSlug) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/leave';
    }
}
