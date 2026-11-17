<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class CopyRecipeRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $teamSlug,
        private readonly int $id,
        private readonly int $targetTeamId,
        private readonly string $clientUuid,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/recipes/'.$this->id.'/copy';
    }

    /**
     * @return array{team_id: int, client_uuid: string}
     */
    protected function defaultBody(): array
    {
        return ['team_id' => $this->targetTeamId, 'client_uuid' => $this->clientUuid];
    }
}
