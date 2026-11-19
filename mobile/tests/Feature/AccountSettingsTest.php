<?php

use App\Http\Integrations\Sunny\Requests\CreateTokenRequest;
use App\Http\Integrations\Sunny\Requests\GetUserRequest;
use App\Http\Integrations\Sunny\Requests\SendVerificationEmailRequest;
use App\Http\Integrations\Sunny\Requests\UpdatePasswordRequest;
use App\Http\Integrations\Sunny\Requests\UpdateProfileRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyConnector;
use App\Http\Integrations\Sunny\SunnyStore;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Support\Facades\DB;
use Native\Mobile\Testing\Native;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function (): void {
    seedSunnyData();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
});

function rememberAndy(bool $verified = true): void
{
    DB::table('sunny_accounts')->updateOrInsert(['server' => SunnyStore::server()], [
        'user_id' => 7,
        'name' => 'Andy Swick',
        'email' => 'andy@swick.family',
        'email_verified_at' => $verified ? now() : null,
    ]);
}

/**
 * @return array<string, mixed>
 */
function sunnyUser(array $attributes = []): array
{
    return ['id' => 7, 'name' => 'Andy Swick', 'email' => 'andy@swick.family', 'email_verified_at' => '2026-11-01T00:00:00.000000Z', ...$attributes];
}

function offlineResponse(): MockResponse
{
    return MockResponse::make()->throw(fn (PendingRequest $pending) => new FatalRequestException(new ConnectException('Could not resolve host', new GuzzleRequest('PATCH', 'https://sunny.example')), $pending));
}

it('shows the account at the top of preferences', function (): void {
    rememberAndy();

    Native::visit('/preferences', platform: 'ios')
        ->assertSee('Andy Swick')
        ->assertSee('andy@swick.family')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'account' && ($node['props']['leading_value'] ?? null) === 'AS')
        ->assertElement('list_section', fn (array $node): bool => ($node['props']['footer'] ?? null) === 'Two-factor authentication, passkeys and API tokens open on sunny.example.')
        ->assertAccessible()
        ->tap('account')
        ->assertNavigatedTo('/preferences/account');

    Saloon::assertNothingSent();
});

it('downloads the name and email when the phone does not have them yet', function (): void {
    DB::table('sunny_accounts')->insert(['server' => SunnyStore::server(), 'user_id' => 7]);
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => sunnyUser()])]);

    Native::visit('/preferences')
        ->assertSet('profile', ['name' => 'Andy Swick', 'email' => 'andy@swick.family', 'verified' => true])
        ->assertSee('andy@swick.family');
});

it('opens password and security settings', function (): void {
    rememberAndy();
    Native::fakeBridge()->respondTo('Browser.OpenInApp', ['success' => true]);

    Native::visit('/preferences')
        ->tap('security-settings')
        ->assertNoNavigation()
        ->tap('password')
        ->assertNavigatedTo('/preferences/password');

    Native::fakeBridge()->assertCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === app(SunnyConnector::class)->siteUrl().'/settings/security');
});

it('remembers the name and email from logging in', function (): void {
    fakeSecureStorage();
    Saloon::fake([CreateTokenRequest::class => MockResponse::make([...sunnyUser(['email_verified_at' => null]), 'onboarded_at' => '2026-11-01T00:00:00.000000Z', 'token' => 'new-token'])]);

    app(SunnyAuth::class)->login('andy@swick.family', 'secret', 'Sunny Mobile');

    expect(app(SunnyStore::class)->profile())->toBe(['name' => 'Andy Swick', 'email' => 'andy@swick.family', 'verified' => false]);
});

it('forgets the name and email when logging out', function (): void {
    rememberAndy();

    app(SunnyStore::class)->clear();

    expect(app(SunnyStore::class)->profile())->toBeNull();
});

it('changes the name', function (): void {
    rememberAndy();
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => sunnyUser()]),
        UpdateProfileRequest::class => MockResponse::make(['data' => sunnyUser(['name' => 'Andy S.'])]),
    ]);

    Native::visit('/preferences/account', platform: 'ios')
        ->assertNavTitle('Account')
        ->assertSet('name', 'Andy Swick')
        ->assertSet('email', 'andy@swick.family')
        ->assertDontSee('Not verified')
        ->assertAccessible()
        ->input('account-name', 'Andy S.')
        ->tap('account-save')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Saved' && $params['variant'] === 'success')
        ->assertDontSee('Not verified');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof UpdateProfileRequest
        && $request->body()->all() === ['name' => 'Andy S.', 'email' => 'andy@swick.family']);

    expect(app(SunnyStore::class)->profile()['name'])->toBe('Andy S.');
});

it('marks a new email as not verified, says Sunny sent a link, and sends another when asked', function (): void {
    rememberAndy();
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => sunnyUser()]),
        UpdateProfileRequest::class => MockResponse::make(['data' => sunnyUser(['email' => 'andy@swickhome.com', 'email_verified_at' => null])]),
        SendVerificationEmailRequest::class => MockResponse::make([], 202),
    ]);

    Native::visit('/preferences/account', platform: 'ios')
        ->input('account-email', 'andy@swickhome.com')
        ->tap('account-save')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Saved. We sent a link to verify andy@swickhome.com.')
        ->assertSee('Not verified')
        ->assertSee('Verify your new email')
        ->assertSee('We sent a link to andy@swickhome.com. Tap it to confirm it’s yours.')
        ->assertSee('Send it again')
        ->assertAccessible()
        ->tap('account-send-verification')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Verification email sent to andy@swickhome.com');

    Saloon::assertSent(SendVerificationEmailRequest::class);
    expect(app(SunnyStore::class)->profile())->toBe(['name' => 'Andy Swick', 'email' => 'andy@swickhome.com', 'verified' => false]);
});

it('keeps the verification card on the saved email while a different one is being typed', function (): void {
    rememberAndy(verified: false);
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => sunnyUser(['email_verified_at' => null])]),
        SendVerificationEmailRequest::class => MockResponse::make([], 202),
    ]);

    Native::visit('/preferences/account', platform: 'ios')
        ->input('account-email', 'andy@swickhome.com')
        ->assertSee('Check andy@swick.family for a link from us and tap it to confirm it’s yours, or send a new one.')
        ->tap('account-send-verification')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Verification email sent to andy@swick.family')
        ->assertSee('We sent a link to andy@swick.family. Tap it to confirm it’s yours.')
        ->assertDontSee('andy@swickhome.com. Tap');
});

it('hides the verification card once Sunny says the email is verified', function (): void {
    rememberAndy(verified: false);
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => sunnyUser(['email_verified_at' => null])]),
        SendVerificationEmailRequest::class => MockResponse::make([], 204),
    ]);

    Native::visit('/preferences/account')
        ->assertSee('Verify your email')
        ->tap('account-send-verification')
        ->assertSet('verified', true)
        ->assertDontSee('Not verified');

    expect(app(SunnyStore::class)->profile()['verified'])->toBeTrue();
});

it('shows the latest verification state from Sunny when the screen opens', function (): void {
    rememberAndy(verified: false);
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => sunnyUser()])]);

    Native::visit('/preferences/account')
        ->assertSet('verified', true)
        ->assertDontSee('Not verified');
});

it('shows Sunny’s validation errors under the field', function (): void {
    rememberAndy();
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => sunnyUser()]),
        UpdateProfileRequest::class => MockResponse::make(['message' => 'The email has already been taken.', 'errors' => ['email' => ['The email has already been taken.']]], 422),
    ]);

    Native::visit('/preferences/account')
        ->input('account-email', 'jamie@swick.family')
        ->tap('account-save')
        ->assertSet('fieldErrors', ['email' => 'The email has already been taken.'])
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'account-email' && ($node['props']['is_error'] ?? null) === true && ($node['props']['supporting'] ?? null) === 'The email has already been taken.');

    expect(app(SunnyStore::class)->profile()['email'])->toBe('andy@swick.family');
});

it('checks the name and email before contacting Sunny', function (): void {
    rememberAndy();
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => sunnyUser()])]);

    Native::visit('/preferences/account')
        ->input('account-name', '  ')
        ->input('account-email', 'not an email')
        ->tap('account-save')
        ->assertSet('fieldErrors', ['name' => 'Enter your name.', 'email' => 'Enter a valid email address.']);

    Saloon::assertNotSent(UpdateProfileRequest::class);
});

it('keeps account changes on the phone when offline so they can be tried again', function (): void {
    rememberAndy();
    $attempts = 0;
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => sunnyUser()]),
        UpdateProfileRequest::class => function () use (&$attempts): MockResponse {
            return ++$attempts === 1 ? offlineResponse() : MockResponse::make(['data' => sunnyUser(['name' => 'Andy S.'])]);
        },
    ]);

    $screen = Native::visit('/preferences/account')
        ->input('account-name', 'Andy S.')
        ->tap('account-save')
        ->assertSee('You’re offline')
        ->assertSee('Changes to your account need a connection. Nothing’s been saved yet.')
        ->assertSee('Try again')
        ->assertSet('name', 'Andy S.');

    expect(app(SunnyStore::class)->profile()['name'])->toBe('Andy Swick');

    $screen->tap('account-save')
        ->assertSet('offline', null)
        ->assertSee('Save');

    expect(app(SunnyStore::class)->profile()['name'])->toBe('Andy S.');
});

it('does not try to save account changes when the phone knows it is offline', function (): void {
    rememberAndy();
    Native::fakeBridge()->respondTo('Network.Status', ['connected' => false]);
    Saloon::fake([UpdateProfileRequest::class => MockResponse::make(['data' => sunnyUser()])]);

    Native::visit('/preferences/account')
        ->tap('account-save')
        ->assertSet('offline', 'save');

    Saloon::assertNothingSent();
});

it('changes the password', function (): void {
    Saloon::fake([UpdatePasswordRequest::class => MockResponse::make([], 204)]);

    Native::visit('/preferences/password', platform: 'ios')
        ->assertNavTitle('Change password')
        ->assertAccessible();

    Native::visit('/preferences/password')
        ->input('current-password', 'old-secret')
        ->input('new-password', 'new-secret-123')
        ->input('confirm-password', 'new-secret-123')
        ->tap('change-password-save')
        ->assertSet('currentPassword', '')
        ->assertSet('password', '')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Password changed')
        ->assertWentBack();

    Saloon::assertSent(fn (Request $request): bool => $request instanceof UpdatePasswordRequest
        && $request->body()->all() === ['current_password' => 'old-secret', 'password' => 'new-secret-123', 'password_confirmation' => 'new-secret-123']);
});

it('shows a wrong current password under that field', function (): void {
    Saloon::fake([UpdatePasswordRequest::class => MockResponse::make([
        'message' => 'The password is incorrect.',
        'errors' => ['current_password' => ['The password is incorrect.']],
    ], 422)]);

    Native::visit('/preferences/password')
        ->input('current-password', 'wrong')
        ->input('new-password', 'new-secret-123')
        ->input('confirm-password', 'new-secret-123')
        ->tap('change-password-save')
        ->assertSee('The password is incorrect.')
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'current-password' && ($node['props']['is_error'] ?? null) === true)
        ->assertSet('password', 'new-secret-123')
        ->assertNoNavigation();
});

it('checks the new passwords match before contacting Sunny', function (): void {
    Saloon::fake([UpdatePasswordRequest::class => MockResponse::make([], 204)]);

    Native::visit('/preferences/password')
        ->input('current-password', 'old-secret')
        ->input('new-password', 'new-secret-123')
        ->input('confirm-password', 'new-secret-456')
        ->tap('change-password-save')
        ->assertSee('The new passwords don’t match.');

    Saloon::assertNothingSent();
});

it('keeps the password unchanged when offline', function (): void {
    Saloon::fake([UpdatePasswordRequest::class => offlineResponse()]);

    Native::visit('/preferences/password')
        ->input('current-password', 'old-secret')
        ->input('new-password', 'new-secret-123')
        ->input('confirm-password', 'new-secret-123')
        ->tap('change-password-save')
        ->assertSee('You’re offline')
        ->assertSee('Changing your password needs a connection. Nothing’s been changed yet.')
        ->assertSee('Try again')
        ->assertNoNavigation();
});
