<?php

namespace App\Http\Integrations\Sunny\Requests;

use Saloon\Contracts\Body\HasBody;
use Saloon\Enums\Method;
use Saloon\Http\Request;
use Saloon\Traits\Body\HasJsonBody;

class VerifyTwoFactorRequest extends Request implements HasBody
{
    use HasJsonBody;

    protected Method $method = Method::POST;

    public function __construct(
        #[\SensitiveParameter] private readonly string $challenge,
        #[\SensitiveParameter] private readonly string $code,
        private readonly bool $useRecoveryCode = false,
    ) {}

    public function resolveEndpoint(): string
    {
        return '/sanctum/token/two-factor';
    }

    /**
     * @return array<string, string>
     */
    protected function defaultBody(): array
    {
        return [
            'challenge' => $this->challenge,
            $this->useRecoveryCode ? 'recovery_code' : 'code' => $this->code,
        ];
    }
}
