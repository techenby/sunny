<?php

namespace App\Http\Integrations\Sunny;

use InvalidArgumentException;
use Native\Mobile\Facades\SecureStorage;
use RuntimeException;

class SunnyTokenStore
{
    public function __construct(private readonly SunnyConnector $connector) {}

    public function get(): ?string
    {
        $result = SecureStorage::read($this->key());

        throw_if($result->unavailable(), RuntimeException::class, 'Secure storage is locked. Unlock your device and try again.');
        throw_if($result->failed(), RuntimeException::class, 'Unable to read the Sunny token from secure storage.');

        return $result->missing() ? null : $result->value;
    }

    public function put(#[\SensitiveParameter] string $token): void
    {
        throw_if(trim($token) === '', InvalidArgumentException::class, 'The Sunny token cannot be empty.');
        throw_unless(SecureStorage::set($this->key(), $token), RuntimeException::class, 'Unable to save the Sunny token to secure storage.');
    }

    public function forget(): void
    {
        throw_unless(SecureStorage::delete($this->key()), RuntimeException::class, 'Unable to delete the Sunny token from secure storage.');
    }

    private function key(): string
    {
        return 'sunny.auth.token.'.hash('sha256', $this->connector->resolveBaseUrl());
    }
}
