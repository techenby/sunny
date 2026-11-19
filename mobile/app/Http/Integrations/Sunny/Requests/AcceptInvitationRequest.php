<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class AcceptInvitationRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(private readonly string $code) {}

    public function resolveEndpoint(): string
    {
        return '/invitations/'.rawurlencode($this->code).'/accept';
    }
}
