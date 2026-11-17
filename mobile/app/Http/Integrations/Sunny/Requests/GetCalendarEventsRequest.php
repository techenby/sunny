<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetCalendarEventsRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        private readonly string $teamSlug,
        private readonly string $from,
        private readonly int $days,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/calendar-events';
    }

    /**
     * @return array{from: string, days: int}
     */
    protected function defaultQuery(): array
    {
        return ['from' => $this->from, 'days' => $this->days];
    }

    /**
     * @return array{connect_timeout: int, timeout: int}
     */
    protected function defaultConfig(): array
    {
        return ['connect_timeout' => 4, 'timeout' => 8];
    }
}
