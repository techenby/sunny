<?php

namespace App\Http\Integrations\Sunny;

use App\Actions\DestroySession;
use App\Http\Integrations\Sunny\Requests\CreateTokenRequest;
use App\Http\Integrations\Sunny\Requests\LogoutRequest;
use App\Http\Integrations\Sunny\Requests\RefreshTokenRequest;
use App\Http\Integrations\Sunny\Requests\VerifyTwoFactorRequest;
use Illuminate\Auth\AuthenticationException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Response;
use UnexpectedValueException;

class SunnyAuth
{
    public function __construct(
        private readonly SunnyConnector $connector,
        private readonly SunnyTokenStore $tokens,
        private readonly SunnyStore $store,
        private readonly DestroySession $destroySession,
    ) {}

    /**
     * @return string|null The two-factor challenge to verify, or null once signed in.
     */
    public function login(string $email, #[\SensitiveParameter] string $password, string $deviceName): ?string
    {
        $response = $this->connector->send(new CreateTokenRequest($email, $password, $deviceName));

        if ($response->json('two_factor') !== true) {
            $this->storeToken($response, clearLocalData: true);

            return null;
        }

        $challenge = $response->json('challenge');

        throw_unless(is_string($challenge) && $challenge !== '', UnexpectedValueException::class, 'Sunny did not return a valid two-factor challenge.');

        return $challenge;
    }

    public function verifyTwoFactor(
        #[\SensitiveParameter] string $challenge,
        #[\SensitiveParameter] string $code,
        bool $useRecoveryCode = false,
    ): void {
        $response = $this->connector->send(new VerifyTwoFactorRequest($challenge, $code, $useRecoveryCode));
        $this->storeToken($response, clearLocalData: true);
    }

    public function authenticatedConnector(#[\SensitiveParameter] ?string $token = null): SunnyConnector
    {
        $token ??= $this->tokens->get();

        throw_if($token === null || $token === '', AuthenticationException::class, 'Sign in to Sunny first.');

        return (clone $this->connector)->authenticate(new TokenAuthenticator($token));
    }

    public function refresh(): void
    {
        $this->storeToken($this->authenticatedConnector()->send(new RefreshTokenRequest));
    }

    public function logout(): void
    {
        $token = $this->tokens->get();

        if ($token === null) {
            $this->store->clear();

            return;
        }

        try {
            (clone $this->connector)->authenticate(new TokenAuthenticator($token))->send(new LogoutRequest);
        } catch (UnauthorizedException) {
            // An expired or revoked token is already signed out on the server.
        } finally {
            $this->destroySession->handle();
        }
    }

    private function storeToken(Response $response, bool $clearLocalData = false): void
    {
        $token = $response->json('token');

        throw_unless(is_string($token) && trim($token) !== '', UnexpectedValueException::class, 'Sunny did not return a valid authentication token.');

        if ($clearLocalData) {
            $this->store->clear();
        }

        $this->tokens->put($token);
    }
}
