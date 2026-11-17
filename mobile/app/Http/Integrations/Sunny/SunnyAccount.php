<?php

namespace App\Http\Integrations\Sunny;

use App\Http\Integrations\Sunny\Requests\GetUserRequest;
use App\Http\Integrations\Sunny\Requests\SendVerificationEmailRequest;
use App\Http\Integrations\Sunny\Requests\UpdatePasswordRequest;
use App\Http\Integrations\Sunny\Requests\UpdateProfileRequest;

class SunnyAccount
{
    public function __construct(
        private readonly SunnyAuth $auth,
        private readonly SunnyStore $store,
    ) {}

    /** @return array{name: string, email: string, verified: bool}|null */
    public function refresh(#[\SensitiveParameter] ?string $token = null): ?array
    {
        $this->store->rememberProfile($this->auth->authenticatedConnector($token)->send(new GetUserRequest)->json('data'));

        return $this->store->profile();
    }

    /** @return array{name: string, email: string, verified: bool}|null */
    public function update(string $name, string $email): ?array
    {
        $this->store->rememberProfile($this->auth->authenticatedConnector()->send(new UpdateProfileRequest($name, $email))->json('data'));

        return $this->store->profile();
    }

    public function updatePassword(
        #[\SensitiveParameter] string $currentPassword,
        #[\SensitiveParameter] string $password,
        #[\SensitiveParameter] string $passwordConfirmation,
    ): void {
        $this->auth->authenticatedConnector()->send(new UpdatePasswordRequest($currentPassword, $password, $passwordConfirmation));
    }

    public function sendVerificationEmail(): bool
    {
        if ($this->auth->authenticatedConnector()->send(new SendVerificationEmailRequest)->status() === 202) {
            return true;
        }

        $this->store->rememberProfile([...$this->store->profile() ?? [], 'email_verified_at' => now()->toIso8601String()]);

        return false;
    }
}
