<?php

use App\Http\Integrations\Sunny\Requests\CreateTokenRequest;
use App\Http\Integrations\Sunny\Requests\GetUserRequest;
use App\Http\Integrations\Sunny\Requests\LogoutRequest;
use App\Http\Integrations\Sunny\Requests\RefreshTokenRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\Requests\VerifyTwoFactorRequest;
use App\Http\Integrations\Sunny\SunnyConnector;
use Saloon\Config;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Auth\TokenAuthenticator;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function (): void {
    config(['services.sunny.api_url' => 'https://sunny.example/api/']);
    Config::preventStrayRequests();
});

it('sends login credentials as JSON and exposes tokens or two factor challenges', function (array $body): void {
    Saloon::fake([CreateTokenRequest::class => MockResponse::make($body)]);

    $response = app(SunnyConnector::class)->send(new CreateTokenRequest('person@example.com', 'secret', 'My phone'));

    expect($response->json())->toBe($body);

    Saloon::assertSent(function (Request $request, Response $response): bool {
        $psrRequest = $response->getPendingRequest()->createPsrRequest();

        return (string) $psrRequest->getUri() === 'https://sunny.example/api/sanctum/token'
            && $psrRequest->getMethod() === 'POST'
            && $psrRequest->getHeaderLine('Accept') === 'application/json'
            && $psrRequest->getHeaderLine('Content-Type') === 'application/json'
            && ! $psrRequest->hasHeader('Authorization')
            && json_decode((string) $psrRequest->getBody(), true) === [
                'email' => 'person@example.com',
                'password' => 'secret',
                'device_name' => 'My phone',
            ];
    });
})->with([
    'token' => [['id' => 1, 'token' => 'test-token', 'expires_at' => '2026-10-23T12:00:00+00:00']],
    'two factor' => [['two_factor' => true, 'challenge' => 'test-challenge']],
]);

it('verifies two factor challenges with a code or recovery code', function (bool $recovery, string $field): void {
    Saloon::fake([VerifyTwoFactorRequest::class => MockResponse::make(['token' => 'test-token'])]);

    app(SunnyConnector::class)->send(new VerifyTwoFactorRequest('challenge', '012345', $recovery));

    Saloon::assertSent(function (Request $request, Response $response) use ($field): bool {
        $psrRequest = $response->getPendingRequest()->createPsrRequest();

        return (string) $psrRequest->getUri() === 'https://sunny.example/api/sanctum/token/two-factor'
            && $psrRequest->getMethod() === 'POST'
            && json_decode((string) $psrRequest->getBody(), true) === ['challenge' => 'challenge', $field => '012345'];
    });
})->with([
    'authenticator' => [false, 'code'],
    'recovery' => [true, 'recovery_code'],
]);

it('sends authenticated user, refresh and logout requests', function (string $requestClass, string $method, string $endpoint, int $status): void {
    Saloon::fake([$requestClass => MockResponse::make([], $status)]);
    $connector = app(SunnyConnector::class)->authenticate(new TokenAuthenticator('test-token'));

    expect($connector->send(new $requestClass)->status())->toBe($status);

    Saloon::assertSent(function (Request $request, Response $response) use ($method, $endpoint): bool {
        $psrRequest = $response->getPendingRequest()->createPsrRequest();

        return (string) $psrRequest->getUri() === 'https://sunny.example/api'.$endpoint
            && $psrRequest->getMethod() === $method
            && $psrRequest->getHeaderLine('Authorization') === 'Bearer test-token';
    });

    expect(app(SunnyConnector::class)->getAuthenticator())->toBeNull();
})->with([
    'user' => [GetUserRequest::class, 'GET', '/user', 200],
    'refresh' => [RefreshTokenRequest::class, 'POST', '/sanctum/token/refresh', 200],
    'logout' => [LogoutRequest::class, 'POST', '/logout', 204],
]);

it('requests full or incremental sync', function (?string $since): void {
    Saloon::fake([SyncRequest::class => MockResponse::make(['teams' => [], 'items' => [], 'recipes' => [], 'synced_at' => '2026-09-23T12:00:00+00:00'])]);

    $connector = app(SunnyConnector::class)->authenticate(new TokenAuthenticator('test-token'));
    $connector->send(new SyncRequest($since === null ? null : new DateTimeImmutable($since)));

    Saloon::assertSent(function (Request $request, Response $response) use ($since): bool {
        $psrRequest = $response->getPendingRequest()->createPsrRequest();
        parse_str($psrRequest->getUri()->getQuery(), $query);

        return $psrRequest->getUri()->getPath() === '/api/sync'
            && $psrRequest->getMethod() === 'GET'
            && $psrRequest->getHeaderLine('Authorization') === 'Bearer test-token'
            && $query === ($since === null ? [] : ['since' => $since]);
    });
})->with([null, '2026-09-22T12:00:00+05:30']);

it('throws API errors while preserving their response details', function (int $status): void {
    $body = ['message' => 'Request failed.', 'errors' => ['email' => ['Invalid credentials.']]];
    Saloon::fake([CreateTokenRequest::class => MockResponse::make($body, $status)]);

    try {
        app(SunnyConnector::class)->send(new CreateTokenRequest('person@example.com', 'secret', 'My phone'));
        test()->fail('Expected an API exception.');
    } catch (RequestException $exception) {
        expect($exception->getResponse()->status())->toBe($status)
            ->and($exception->getResponse()->json())->toBe($body);
    }

    Saloon::assertSentCount(1);
})->with([401, 422, 429, 500]);

it('rejects missing or invalid API configuration', function (?string $url): void {
    config(['services.sunny.api_url' => $url]);

    expect(fn () => app(SunnyConnector::class))->toThrow(InvalidArgumentException::class, 'SUNNY_API_URL');
})->with([null, '', '/api', 'ftp://sunny.example/api']);

it('resolves the development CA inside the app only in the local environment', function (string $environment): void {
    app()->instance('env', $environment);
    config(['services.sunny.dev_ca_bundle' => 'tests/Feature/SunnyConnectorTest.php']);

    expect(app(SunnyConnector::class)->config()->get('verify'))->toBe(
        $environment === 'local' ? base_path('tests/Feature/SunnyConnectorTest.php') : true,
    );
})->with(['local', 'testing', 'staging', 'production']);

it('keeps certificate verification enabled when no development CA is configured', function (): void {
    app()->instance('env', 'local');
    config(['services.sunny.dev_ca_bundle' => null]);

    expect(app(SunnyConnector::class)->config()->get('verify'))->toBeTrue();
});

it('fails instead of disabling verification when the development CA is missing', function (): void {
    app()->instance('env', 'local');
    config(['services.sunny.dev_ca_bundle' => 'resources/certificates/missing.pem']);

    expect(fn () => app(SunnyConnector::class))->toThrow(InvalidArgumentException::class, 'readable PEM file');
});
