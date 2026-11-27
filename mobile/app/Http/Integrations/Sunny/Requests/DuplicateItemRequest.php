<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class DuplicateItemRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $teamSlug,
        private readonly int $id,
        private readonly int $count,
        private readonly ?string $clientUuid = null,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/items/'.$this->id.'/duplicate';
    }

    /**
     * @return array{count: int, client_uuid?: string}
     */
    protected function defaultBody(): array
    {
        return array_filter(['count' => $this->count, 'client_uuid' => $this->clientUuid], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @return array{timeout: int}
     */
    protected function defaultConfig(): array
    {
        return ['timeout' => 120];
    }
}
