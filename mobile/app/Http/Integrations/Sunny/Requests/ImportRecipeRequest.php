<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class ImportRecipeRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $teamSlug,
        private readonly string $url,
        private readonly string $clientUuid,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/recipes/import';
    }

    /**
     * @return array{url: string, client_uuid: string}
     */
    protected function defaultBody(): array
    {
        return ['url' => $this->url, 'client_uuid' => $this->clientUuid];
    }
}
