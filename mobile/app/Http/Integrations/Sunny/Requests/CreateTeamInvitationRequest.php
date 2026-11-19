<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class CreateTeamInvitationRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(private readonly string $teamSlug, private readonly string $email, private readonly string $role) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/invitations';
    }

    /**
     * @return array{email: string, role: string}
     */
    protected function defaultBody(): array
    {
        return ['email' => $this->email, 'role' => $this->role];
    }
}
