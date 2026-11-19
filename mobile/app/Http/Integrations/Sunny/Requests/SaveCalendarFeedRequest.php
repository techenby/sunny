<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class SaveCalendarFeedRequest extends Request implements HasBody
{
    use HasJsonBody;

    public function __construct(
        private readonly string $teamSlug,
        private readonly array $payload,
        private readonly ?int $id = null,
    ) {
        $this->method = $id === null ? Method::POST : Method::PATCH;
    }

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/calendar-feeds'.($this->id !== null ? '/'.$this->id : '');
    }

    protected function defaultBody(): array
    {
        return $this->payload;
    }

    /**
     * @return array{connect_timeout: int, timeout: int}
     */
    protected function defaultConfig(): array
    {
        return ['connect_timeout' => 4, 'timeout' => 15];
    }
}
