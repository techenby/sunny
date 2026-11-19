<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetRoutineOccurrencesRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $teamSlug,
        private readonly string $date,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/routine-occurrences';
    }

    /**
     * @return array{date: string}
     */
    protected function defaultQuery(): array
    {
        return ['date' => $this->date];
    }
}
