<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class MoveItemRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $teamSlug,
        private readonly int $id,
        private readonly int $targetTeamId,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/items/'.$this->id.'/move';
    }

    /**
     * @return array{team_id: int}
     */
    protected function defaultBody(): array
    {
        return ['team_id' => $this->targetTeamId];
    }
}
