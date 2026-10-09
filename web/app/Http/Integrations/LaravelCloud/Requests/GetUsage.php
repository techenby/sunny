<?php

declare(strict_types=1);

namespace App\Http\Integrations\LaravelCloud\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class GetUsage extends Request
{
    protected Method $method = Method::GET;

    public function __construct(
        public readonly int $period = 0,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/usage';
    }

    public function defaultQuery(): array
    {
        return [
            'period' => $this->period,
        ];
    }
}
