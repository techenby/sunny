<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class UpdateRoutineStepRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::PATCH;

    public function __construct(
        private readonly string $teamSlug,
        private readonly int $occurrenceId,
        private readonly int $id,
        private readonly bool $completed,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/teams/'.rawurlencode($this->teamSlug).'/routine-occurrences/'.$this->occurrenceId.'/steps/'.$this->id;
    }

    /**
     * @return array{completed: bool}
     */
    protected function defaultBody(): array
    {
        return ['completed' => $this->completed];
    }
}
