<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ShareRecipeRequest extends Request
{
    protected Method $method;

    public function __construct(
        private readonly string $teamSlug,
        private readonly int $id,
        bool $shared,
    ) {
        $this->method = $shared ? Method::PUT : Method::DELETE;
    }

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/recipes/'.$this->id.'/share';
    }
}
