<?php

namespace App\Http\Integrations\Sunny\Requests;

use DateTimeInterface;
use Saloon\Enums\Method;
use Saloon\Http\Request;

class SyncRequest extends Request
{
    protected Method $method = Method::GET;

    public function __construct(private readonly ?DateTimeInterface $since = null) {}

    public function resolveEndpoint(): string
    {
        return '/sync';
    }

    /**
     * @return array{since?: string}
     */
    protected function defaultQuery(): array
    {
        return $this->since === null ? [] : ['since' => $this->since->format(DateTimeInterface::ATOM)];
    }
}
