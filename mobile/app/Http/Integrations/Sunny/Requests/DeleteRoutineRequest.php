<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class DeleteRoutineRequest extends Request
{
    protected Method $method = Method::DELETE;

    public function __construct(private readonly string $teamSlug, private readonly int $id) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/routines/'.$this->id;
    }
}
