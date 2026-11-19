<?php

namespace App\Http\Integrations\Sunny;

use App\Actions\DestroySession;
use App\Http\Integrations\Sunny\Requests\CreateTokenRequest;
use App\Http\Integrations\Sunny\Requests\DeleteAccountRequest;
use App\Http\Integrations\Sunny\Requests\LogoutRequest;
use App\Http\Integrations\Sunny\Requests\RefreshTokenRequest;
use App\Http\Integrations\Sunny\Requests\RegisterRequest;
use App\Http\Integrations\Sunny\Requests\SendPasswordResetLinkRequest;
use App\Http\Integrations\Sunny\Requests\VerifyTwoFactorRequest;
use App\Models\Onboarding;
use Illuminate\Auth\AuthenticationException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Request;
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
            $this->storeToken($response->json(), clearLocalData: true);

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
        $this->storeToken($response->json(), clearLocalData: true);
    }

    /**
     * @return array{status: int, body: array<array-key, mixed>}
     */
    public function register(
        string $name,
        string $email,
        #[\SensitiveParameter] string $password,
        #[\SensitiveParameter] string $passwordConfirmation,
        string $deviceName,
    ): array {
        return $this->attempt(new RegisterRequest($name, $email, $password, $passwordConfirmation, $deviceName));
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    public function signIn(array $body): void
    {
        $this->storeToken($body, clearLocalData: true);
    }

    /**
     * @return array{status: int, body: array<array-key, mixed>}
     */
    public function sendPasswordResetLink(string $email): array
    {
        return $this->attempt(new SendPasswordResetLinkRequest($email));
    }

    public function authenticatedConnector(#[\SensitiveParameter] ?string $token = null): SunnyConnector
    {
        $token ??= $this->tokens->get();

        throw_if($token === null || $token === '', AuthenticationException::class, 'Sign in to Sunny first.');

        return (clone $this->connector)->authenticate(new TokenAuthenticator($token));
    }

    public function refresh(): void
    {
        $this->storeToken($this->authenticatedConnector()->send(new RefreshTokenRequest)->json());
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

    public function deleteAccount(#[\SensitiveParameter] string $password): void
    {
        $this->authenticatedConnector()->send(new DeleteAccountRequest($password));

        $this->store->startSession(null);
        $this->destroySession->handle();
    }

    /**
     * @return array{status: int, body: array<array-key, mixed>}
     */
    private function attempt(Request $request): array
    {
        try {
            $response = $this->connector->send($request);
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
        } catch (FatalRequestException) {
            return ['status' => 0, 'body' => []];
        }

        $body = rescue(fn (): mixed => $response->json(), [], report: false);

        return ['status' => $response->status(), 'body' => is_array($body) ? $body : []];
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private function storeToken(array $body, bool $clearLocalData = false): void
    {
        $token = $body['token'] ?? null;

        throw_unless(is_string($token) && trim($token) !== '', UnexpectedValueException::class, 'Sunny did not return a valid authentication token.');

        if ($clearLocalData) {
            $userId = $body['id'] ?? null;
            $this->store->startSession(is_int($userId) ? $userId : null);
            $this->store->rememberProfile($body);

            if (is_int($userId) && array_key_exists('onboarded_at', $body) && $body['onboarded_at'] === null) {
                Onboarding::begin($userId, is_string($body['name'] ?? null) ? $body['name'] : null);
            }
            SunnySyncCoordinator::forgetDispatch();
        }

        $this->tokens->put($token);
    }
}
