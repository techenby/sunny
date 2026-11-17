<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Enums\Method;
use Saloon\Http\Request;

class ChecklistActionRequest extends Request
{
    protected Method $method = Method::POST;

    public function __construct(
        private readonly string $teamSlug,
        private readonly int $checklistId,
        private readonly string $action,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/checklists/'.$this->checklistId.'/'.$this->action;
    }
}
