<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteCalendarFeedRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(
        private readonly string $teamSlug,
        private readonly int $id,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/calendar-feeds/'.$this->id;
    }

    /**
     * @return array{connect_timeout: int, timeout: int}
     */
    protected function defaultConfig(): array
    {
        return ['connect_timeout' => 4, 'timeout' => 8];
    }
}
