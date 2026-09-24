<?php

use App\Http\Integrations\Sunny\Requests\CreateTokenRequest;
use App\Http\Integrations\Sunny\Requests\GetUserRequest;
use App\Http\Integrations\Sunny\Requests\LogoutRequest;
use App\Http\Integrations\Sunny\Requests\VerifyTwoFactorRequest;
use App\Http\Integrations\Sunny\SunnyStore;
use Native\Mobile\Testing\Native;
use Saloon\Config;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function (): void {
    config(['services.sunny.api_url' => 'https://sunny.example/api']);
    Config::preventStrayRequests();
    seedSunnyData();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => ''])
        ->respondTo('SecureStorage.Set', ['success' => true])
        ->respondTo('SecureStorage.Delete', ['success' => true]);
});

it('logs in and clears the password after securely saving the token', function (): void {
    Saloon::fake([CreateTokenRequest::class => MockResponse::make(['token' => 'test-token'])]);
    Native::visit('/login')->input('login-email', 'person@example.com')->input('login-password', 'secret')
        ->tap('login-submit')->assertSet('password', '')->assertReplacedWith('/dashboard');
    Native::fakeBridge()->assertCalled('SecureStorage.Set', fn (array $params): bool => $params['value'] === 'test-token');
});

it('completes two factor authentication with either code type', function (bool $recovery): void {
    Saloon::fake([
        CreateTokenRequest::class => MockResponse::make(['two_factor' => true, 'challenge' => 'challenge']),
        VerifyTwoFactorRequest::class => MockResponse::make(['token' => 'verified-token']),
    ]);
    $screen = Native::visit('/login')->input('login-email', 'person@example.com')->input('login-password', 'secret')
        ->tap('login-submit')->assertSet('password', '')->assertSet('twoFactor', true)->assertNoNavigation();
    Native::fakeBridge()->assertNotCalled('SecureStorage.Set');
    if ($recovery) {
        $screen->tap('login-recovery')->assertSee('Recovery code');
    }
    $screen->input('login-code', '012345')->tap('login-submit')->assertSet('code', '')->assertReplacedWith('/dashboard');
    Saloon::assertSent(fn (Request $request): bool => $request instanceof VerifyTwoFactorRequest
        && $request->body()->all() === ['challenge' => 'challenge', $recovery ? 'recovery_code' : 'code' => '012345']);
})->with([false, true]);

it('shows login errors without navigating or saving credentials', function (int $status, string $message): void {
    Saloon::fake([CreateTokenRequest::class => MockResponse::make([], $status)]);
    Native::visit('/login')->input('login-email', 'person@example.com')->input('login-password', 'secret')
        ->tap('login-submit')->assertSee($message)->assertSet('password', '')->assertNoNavigation();
    Native::fakeBridge()->assertNotCalled('SecureStorage.Set');
})->with([
    [422, 'Check your email and password and try again.'],
    [429, 'Too many attempts. Please wait before trying again.'],
    [500, 'Sunny is unavailable right now. Please try again.'],
]);

it('stays on login when saving the token fails', function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Set', ['success' => false]);
    Saloon::fake([CreateTokenRequest::class => MockResponse::make(['token' => 'test-token'])]);
    Native::visit('/login')->input('login-email', 'person@example.com')->input('login-password', 'secret')
        ->tap('login-submit')->assertSee('Unable to securely complete login. Unlock your device and try again.')->assertNoNavigation();
});

it('allows retrying an invalid code and restarting an expired challenge', function (): void {
    Saloon::fake([
        CreateTokenRequest::class => MockResponse::make(['two_factor' => true, 'challenge' => 'challenge']),
        VerifyTwoFactorRequest::class => MockResponse::make([], 422),
    ]);
    Native::visit('/login')->input('login-email', 'person@example.com')->input('login-password', 'secret')->tap('login-submit')
        ->input('login-code', 'bad-code')->tap('login-submit')->assertSet('twoFactor', true)
        ->assertSee('The code is invalid or expired. Try again or restart login.')->assertNoNavigation()
        ->tap('login-restart')->assertSet('twoFactor', false)->assertSet('code', '')->assertSet('password', '');
});

it('restores a valid saved session', function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    Saloon::fake([GetUserRequest::class => MockResponse::make(['id' => 1])]);
    Native::visit('/')->assertReplacedWith('/dashboard');
});

it('deletes expired sessions but preserves tokens during outages', function (int $status): void {
    $bridge = Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    Saloon::fake([GetUserRequest::class => MockResponse::make([], $status)]);
    app(SunnyStore::class)->clear();
    $screen = Native::visit('/')->assertNoNavigation();
    if ($status === 401) {
        $bridge->assertCalled('SecureStorage.Delete');
    } else {
        $bridge->assertNotCalled('SecureStorage.Delete');
        $screen->assertSee('Unable to check your session. Check your connection and try again.');
    }
})->with([401, 500]);

it('retries session restoration after storage is unlocked', function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['status' => 'unavailable']);
    Saloon::fake([GetUserRequest::class => MockResponse::make(['id' => 1])]);
    $screen = Native::visit('/')->assertNoNavigation()->assertSee('Unable to read your saved login. Unlock your device and try again.');
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    $screen->tap('session-retry')->assertReplacedWith('/dashboard');
});

it('logs out even when remote revocation fails', function (int $status): void {
    $bridge = Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    Saloon::fake([LogoutRequest::class => MockResponse::make([], $status)]);
    Native::visit('/dashboard')->tap('Log out')->assertReplacedWith('/');
    $bridge->assertCalled('SecureStorage.Delete');
})->with([204, 500]);

it('does not pretend logout succeeded when deleting the token fails', function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token'])->respondTo('SecureStorage.Delete', ['success' => false]);
    Saloon::fake([LogoutRequest::class => MockResponse::make([], 204)]);
    Native::visit('/dashboard')->tap('Log out')->assertNoNavigation();
});

it('handles redirects and malformed JSON without crashing the login screen', function (int $status, string $message): void {
    Saloon::fake([CreateTokenRequest::class => MockResponse::make('<html>Not an API response</html>', $status, ['Content-Type' => 'text/html'])]);

    Native::visit('/login')->input('login-email', 'person@example.com')->input('login-password', 'secret')
        ->tap('login-submit')->assertSee($message)->assertSet('password', '')->assertNoNavigation();

    Native::fakeBridge()->assertNotCalled('SecureStorage.Set');
    Saloon::assertSentCount(1);
})->with([
    'HTTPS redirect' => [301, 'Sunny redirected the login request. Check the configured API URL uses HTTPS.'],
    'HTML success' => [200, 'Sunny returned an invalid response. Check the API URL and try again.'],
]);
