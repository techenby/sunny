<?php

declare(strict_types=1);

namespace App\Http\Integrations\Bento\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class CreateDataDeletionRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(public readonly string $email) {}

    public function resolveEndpoint(): string
    {
        return '/data_deletion_requests';
    }

    /** @return array<string, mixed> */
    protected function defaultBody(): array
    {
        return [
            'site_uuid' => config('bentonow.site_uuid'),
            'data_deletion_request' => ['email' => $this->email],
        ];
    }
}
