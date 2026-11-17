<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class SaveSharedRecipeRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $token,
        private readonly int $teamId,
        private readonly string $clientUuid,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/shared-recipes/'.rawurlencode($this->token).'/save';
    }

    /**
     * @return array{team_id: int, client_uuid: string}
     */
    protected function defaultBody(): array
    {
        return ['team_id' => $this->teamId, 'client_uuid' => $this->clientUuid];
    }
}
