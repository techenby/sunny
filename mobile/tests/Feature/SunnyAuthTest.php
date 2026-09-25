<?php

use App\Http\Integrations\Sunny\Requests\CreateTokenRequest;
use App\Http\Integrations\Sunny\Requests\GetUserRequest;
use App\Http\Integrations\Sunny\Requests\LogoutRequest;
use App\Http\Integrations\Sunny\Requests\RefreshTokenRequest;
use App\Http\Integrations\Sunny\Requests\VerifyTwoFactorRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use Illuminate\Auth\AuthenticationException;
use Native\Mobile\Testing\FakeBridge;
use Saloon\Config;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Http\Response;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function (): void {
    config(['services.sunny.api_url' => 'https://sunny.example/api']);
    Config::preventStrayRequests();
});

afterEach(fn () => FakeBridge::disable());

it('stores a login token and reads it for authenticated requests', function (): void {
    $bridge = FakeBridge::enable()->respondTo('SecureStorage.Set', ['success' => true])
        ->respondTo('SecureStorage.Get', ['value' => 'fresh-token']);
    Saloon::fake([
        CreateTokenRequest::class => MockResponse::make(['token' => 'fresh-token']),
        GetUserRequest::class => MockResponse::make(['id' => 1]),
    ]);

    $auth = app(SunnyAuth::class);
    $auth->login('person@example.com', 'secret', 'My phone');
    $bridge->assertCalled('SecureStorage.Set', fn (array $params): bool => $params['value'] === 'fresh-token');
    $auth->authenticatedConnector()->send(new GetUserRequest);
    Saloon::assertSent(fn (Request $request, Response $response): bool => $request instanceof GetUserRequest
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer fresh-token');

    $auth->login('person@example.com', 'secret', 'My phone');
    Saloon::assertSent(fn (Request $request, Response $response): bool => $request instanceof CreateTokenRequest
        && $response->getPendingRequest()->headers()->get('Authorization') === null);
});

it('waits for two factor verification before storing a token', function (bool $recovery): void {
    $bridge = FakeBridge::enable()->respondTo('SecureStorage.Set', ['success' => true]);
    Saloon::fake([
        CreateTokenRequest::class => MockResponse::make(['two_factor' => true, 'challenge' => 'challenge']),
        VerifyTwoFactorRequest::class => MockResponse::make(['token' => 'verified-token']),
    ]);
    $auth = app(SunnyAuth::class);
    expect($auth->login('person@example.com', 'secret', 'My phone')->json('challenge'))->toBe('challenge');
    $bridge->assertNotCalled('SecureStorage.Set');
    $auth->verifyTwoFactor('challenge', '012345', $recovery);
    $bridge->assertCalled('SecureStorage.Set', fn (array $params): bool => $params['value'] === 'verified-token');
})->with([false, true]);

it('replaces the secure token after refreshing and reads the replacement on the next request', function (): void {
    $token = 'old-token';
    FakeBridge::enable()->respondTo('SecureStorage.Get', function () use (&$token): array {
        return ['value' => $token];
    })->respondTo('SecureStorage.Set', function (array $params) use (&$token): array {
        $token = $params['value'];

        return ['success' => true];
    });
    Saloon::fake([
        RefreshTokenRequest::class => MockResponse::make(['token' => 'new-token']),
        GetUserRequest::class => MockResponse::make(['id' => 1]),
    ]);
    $auth = app(SunnyAuth::class);
    $auth->refresh();
    Saloon::assertSent(fn (Request $request, Response $response): bool => $request instanceof RefreshTokenRequest
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer old-token');
    expect($token)->toBe('new-token');
    $auth->authenticatedConnector()->send(new GetUserRequest);
    Saloon::assertSent(fn (Request $request, Response $response): bool => $request instanceof GetUserRequest
        && $response->getPendingRequest()->headers()->get('Authorization') === 'Bearer new-token');
});

it('clears local credentials on logout including server failures', function (int $status): void {
    $bridge = FakeBridge::enable()->respondTo('SecureStorage.Get', ['value' => 'token'])
        ->respondTo('SecureStorage.Delete', ['success' => true]);
    Saloon::fake([LogoutRequest::class => MockResponse::make([], $status)]);

    if ($status === 500) {
        expect(fn () => app(SunnyAuth::class)->logout())->toThrow(RequestException::class);
    } else {
        app(SunnyAuth::class)->logout();
    }

    $bridge->assertCalled('SecureStorage.Delete');
    Saloon::assertSentCount(1);
})->with([204, 401, 500]);

it('requires a stored token for authenticated calls and allows an already signed out logout', function (): void {
    FakeBridge::enable()->respondTo('SecureStorage.Get', ['value' => '']);
    Saloon::fake([]);
    expect(fn () => app(SunnyAuth::class)->authenticatedConnector())->toThrow(AuthenticationException::class);
    app(SunnyAuth::class)->logout();
    Saloon::assertNothingSent();
});

it('does not store tokens from unsuccessful or malformed login responses', function (array $body, int $status, string $exception): void {
    $bridge = FakeBridge::enable();
    Saloon::fake([CreateTokenRequest::class => MockResponse::make($body, $status)]);

    expect(fn () => app(SunnyAuth::class)->login('person@example.com', 'secret', 'My phone'))->toThrow($exception);
    $bridge->assertNotCalled('SecureStorage.Set');
})->with([
    'invalid credentials' => [['message' => 'Invalid credentials'], 422, RequestException::class],
    'missing token' => [[], 200, UnexpectedValueException::class],
    'empty token' => [['token' => ''], 200, UnexpectedValueException::class],
]);

it('does not report login success when secure storage fails', function (): void {
    FakeBridge::enable()->respondTo('SecureStorage.Set', ['success' => false]);
    Saloon::fake([CreateTokenRequest::class => MockResponse::make(['token' => 'fresh-token'])]);

    expect(fn () => app(SunnyAuth::class)->login('person@example.com', 'secret', 'My phone'))->toThrow(RuntimeException::class);
});
