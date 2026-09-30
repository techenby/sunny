<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteRecordRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        private readonly string $teamSlug,
        private readonly string $collection,
        private readonly int $id,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/'.$this->collection.'/'.$this->id;
    }
}
