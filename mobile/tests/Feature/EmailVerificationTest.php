<?php

use App\Http\Integrations\Sunny\EmailVerificationRequired;
use App\Http\Integrations\Sunny\Requests\CreateTokenRequest;
use App\Http\Integrations\Sunny\Requests\GetUserRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\SendVerificationEmailRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\Requests\UpdateProfileRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Models\PendingWrite;
use Illuminate\Support\Facades\DB;
use Native\Mobile\Testing\Native;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function (): void {
    seedSunnyData();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token'])
        ->respondTo('SecureStorage.Delete', ['success' => true]);
    DB::table('sunny_accounts')->updateOrInsert(['server' => SunnyStore::server()], [
        'user_id' => 7,
        'name' => 'Andy Swick',
        'email' => 'andy@swick.family',
        'email_verified_at' => null,
    ]);
});

function unverifiedUser(array $attributes = []): array
{
    return ['id' => 7, 'name' => 'Andy Swick', 'email' => 'andy@swick.family', 'email_verified_at' => null, ...$attributes];
}

it('opens the verify screen on launch for an unverified account', function (): void {
    Native::visit('/')->assertReplacedWith('/verify-email');
});

it('opens Summary on launch once the account is verified', function (): void {
    DB::table('sunny_accounts')->update(['email_verified_at' => now()]);

    Native::visit('/')->assertReplacedWith('/dashboard');
});

it('stops syncing and keeps queued changes when Sunny asks for a verified email', function (): void {
    DB::table('sunny_accounts')->update(['email_verified_at' => now()]);
    app(SunnyOutbox::class)->queue('checklists', 1, ['name' => 'Hardware store', 'type' => 'shopping']);
    Saloon::fake([
        SaveRecordRequest::class => MockResponse::make(['message' => 'Verify your email address to keep using SunnyHome.', 'email_verification_required' => true], 423),
    ]);

    Native::visit('/dashboard')
        ->call('sync')
        ->assertReplacedWith('/verify-email');

    expect(PendingWrite::query()->whereNull('error')->count())->toBe(1)
        ->and(app(SunnyStore::class)->needsEmailVerification())->toBeTrue();
    Saloon::assertNotSent(SyncRequest::class);
});

it('checks with Sunny instead of syncing while the account is unverified', function (): void {
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()])]);

    expect(fn () => app(SunnySyncCoordinator::class)->sync('saved-token'))->toThrow(EmailVerificationRequired::class);

    Saloon::assertSent(GetUserRequest::class);
    Saloon::assertNotSent(SyncRequest::class);
});

it('syncs once Sunny says the email has been verified', function (): void {
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => unverifiedUser(['email_verified_at' => now()->toIso8601String()])]),
        SyncRequest::class => MockResponse::make([], 503),
    ]);

    expect(fn () => app(SunnySyncCoordinator::class)->sync('saved-token'))->toThrow(RequestException::class);

    Saloon::assertSent(SyncRequest::class);
});

it('carries on once the email is verified', function (): void {
    $responses = [MockResponse::make(['data' => unverifiedUser()]), MockResponse::make(['data' => unverifiedUser(['email_verified_at' => now()->toIso8601String()])])];
    Saloon::fake([GetUserRequest::class => function () use (&$responses): MockResponse {
        return array_shift($responses) ?? MockResponse::make(['data' => unverifiedUser(['email_verified_at' => now()->toIso8601String()])]);
    }]);

    Native::visit('/verify-email')
        ->tap('verify-email-check')
        ->assertReplacedStackWith('/');

    expect(app(SunnyStore::class)->needsEmailVerification())->toBeFalse();
});

it('moves on by itself when the email was verified on the web', function (): void {
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => unverifiedUser(['email_verified_at' => now()->toIso8601String()])])]);

    Native::visit('/verify-email')->assertReplacedStackWith('/');
});

it('signs out from the verify screen when the server no longer accepts the saved login', function (string $button, array $responses): void {
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Soup'], 1);
    Saloon::fake($responses);

    Native::visit('/verify-email')
        ->assertNoNavigation()
        ->tap($button)
        ->assertReplacedStackWith('/')
        ->assertDontSee('The server couldn’t');

    Native::fakeBridge()->assertCalled('SecureStorage.Delete');
    expect(PendingWrite::count())->toBe(1)
        ->and(app(SunnyStore::class)->rememberedAccount())->toBe(7)
        ->and(app(SunnyStore::class)->profile())->toBeNull();
})->with([
    'checking' => ['verify-email-check', fn (): array => [GetUserRequest::class => sequenceOf(MockResponse::make(['data' => unverifiedUser()]), MockResponse::make([], 401))]],
    'sending' => ['verify-email-send', fn (): array => [
        GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()]),
        SendVerificationEmailRequest::class => MockResponse::make([], 401),
    ]],
]);

it('signs out when the background check finds the saved login was revoked', function (): void {
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Soup'], 1);
    Saloon::fake([GetUserRequest::class => MockResponse::make([], 401)]);

    Native::visit('/verify-email')->assertReplacedStackWith('/');

    Native::fakeBridge()->assertCalled('SecureStorage.Delete');
    expect(PendingWrite::count())->toBe(1)->and(app(SunnyStore::class)->rememberedAccount())->toBe(7);
});

it('sends changes queued before a revoked login once the same person signs back in', function (): void {
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Soup'], 1);
    Saloon::fake([GetUserRequest::class => MockResponse::make([], 401)]);
    Native::visit('/verify-email')->assertReplacedStackWith('/');

    Native::fakeBridge()->respondTo('SecureStorage.Set', ['success' => true]);
    Saloon::fake([CreateTokenRequest::class => MockResponse::make(['id' => 7, 'token' => 'new-token'])]);
    app(SunnyAuth::class)->login('andy@swick.family', 'password', 'phone');

    expect(PendingWrite::count())->toBe(1);

    $saved = ['id' => 1, 'team_id' => 1, 'name' => 'Soup'];
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => unverifiedUser(['email_verified_at' => now()->toIso8601String()])]),
        SaveRecordRequest::class => MockResponse::make(['data' => $saved]),
        SyncRequest::class => MockResponse::make([], 503),
    ]);
    rescue(fn () => app(SunnySyncCoordinator::class)->sync(), report: false);

    Saloon::assertSent(fn ($request): bool => $request instanceof SaveRecordRequest && $request->resolveEndpoint() === '/teams/family/recipes/1');
    expect(PendingWrite::count())->toBe(0);
});

it('drops changes queued before a revoked login when someone else signs in on the phone', function (): void {
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Soup'], 1);
    Saloon::fake([GetUserRequest::class => MockResponse::make([], 401)]);
    Native::visit('/verify-email')->assertReplacedStackWith('/');

    Native::fakeBridge()->respondTo('SecureStorage.Set', ['success' => true]);
    Saloon::fake([CreateTokenRequest::class => MockResponse::make(['id' => 8, 'token' => 'other-token'])]);
    app(SunnyAuth::class)->login('zoro@example.com', 'password', 'phone');

    expect(PendingWrite::count())->toBe(0)->and(app(SunnyStore::class)->rememberedAccount())->toBe(8);

    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => ['id' => 8, 'name' => 'Zoro', 'email' => 'zoro@example.com', 'email_verified_at' => now()->toIso8601String()]]),
        SaveRecordRequest::class => MockResponse::make([], 500),
        SyncRequest::class => MockResponse::make([], 503),
    ]);
    rescue(fn () => app(SunnySyncCoordinator::class)->sync(), report: false);

    Saloon::assertNotSent(SaveRecordRequest::class);
});

it('turns only the server’s verification 423 into its own exception', function (array $body, string $exception): void {
    Saloon::fake([GetUserRequest::class => MockResponse::make($body, 423)]);

    expect(fn () => app(SunnyAuth::class)->authenticatedConnector()->send(new GetUserRequest))->toThrow($exception);
})->with([
    'verification required' => [['message' => 'Verify your email address to keep using SunnyHome.', 'email_verification_required' => true], EmailVerificationRequired::class],
    'some other lock' => [['message' => 'Locked'], RequestException::class],
]);

function pressable(string $ref): Closure
{
    return fn (array $node): bool => ($node['ref'] ?? null) === $ref;
}

it('shows the address to verify, with no tab bar', function (): void {
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()])]);

    Native::visit('/verify-email')
        ->assertNavTitle('Verify your email')
        ->assertTabBarHidden()
        ->assertSee('Verify your email address to start using your household.')
        ->assertSee('andy@swick.family')
        ->assertSee('Not verified yet')
        ->assertSee('We’ll email you a link. It opens in your browser, which may ask you to log in. When you come back, this screen moves on by itself.')
        ->assertSee('Send verification email')
        ->assertSee('I’ve verified')
        ->assertSee('Log out')
        ->assertDontSee('Sunny')
        ->assertAccessible();
});

it('sends a verification link and offers Mail on iOS', function (): void {
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()]),
        SendVerificationEmailRequest::class => MockResponse::make([], 202),
    ]);

    Native::visit('/verify-email', platform: 'ios')
        ->tap('verify-email-send')
        ->assertSet('sent', true)
        ->assertSee('Email sent just now')
        ->assertSee('Check your inbox for a link from us.', false)
        ->assertSee('Didn’t get it?')
        ->assertElement('pressable', pressable('verify-email-send-again'))
        ->assertMissingElement('pressable', pressable('verify-email-send'))
        ->assertAccessible()
        ->tap('verify-email-open-mail');

    Native::fakeBridge()->assertBrowsed('message://');
    Saloon::assertSent(SendVerificationEmailRequest::class);
});

it('makes I’ve verified the main button once sent on Android', function (): void {
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()]),
        SendVerificationEmailRequest::class => MockResponse::make([], 202),
    ]);

    Native::visit('/verify-email', platform: 'android')
        ->tap('verify-email-send')
        ->assertSee('Email sent just now')
        ->assertMissingElement('pressable', pressable('verify-email-open-mail'))
        ->assertElement('pressable', pressable('verify-email-check'));
});

it('sends the link again from the sent state', function (): void {
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()]),
        SendVerificationEmailRequest::class => MockResponse::make([], 202),
    ]);

    Native::visit('/verify-email')
        ->tap('verify-email-send')
        ->tap('verify-email-send-again')
        ->assertSet('sent', true)
        ->assertSet('problem', null);

    expect(collect(Saloon::mockClient()->getRecordedResponses())->filter(fn ($response): bool => $response->getRequest() instanceof SendVerificationEmailRequest))->toHaveCount(2);
});

it('says so in an alert when the email still is not verified', function (): void {
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()])]);

    Native::visit('/verify-email')
        ->tap('verify-email-check')
        ->assertNoNavigation()
        ->assertSee('Not verified yet')
        ->assertSee('Open the link we sent to andy@swick.family, then try again.')
        ->assertAccessible();
});

it('explains a refused or failed request in an alert', function (string $button, string $request, int $status, string $title, string $message): void {
    Saloon::fake([
        GetUserRequest::class => sequenceOf(MockResponse::make(['data' => unverifiedUser()]), MockResponse::make([], $status)),
        SendVerificationEmailRequest::class => MockResponse::make([], $status),
    ]);

    Native::visit('/verify-email')
        ->tap($button)
        ->assertNoNavigation()
        ->assertSee($title)
        ->assertSee($message);
})->with([
    'too many sends' => ['verify-email-send', SendVerificationEmailRequest::class, 429, 'Too many tries', 'Wait a minute and try again.'],
    'send failed' => ['verify-email-send', SendVerificationEmailRequest::class, 500, 'Something went wrong on our end', 'No email sent. Try again in a moment.'],
    'check failed' => ['verify-email-check', GetUserRequest::class, 500, 'Something went wrong on our end', 'Couldn’t check just now. Try again in a moment.'],
]);

it('disables everything but Log out while offline', function (bool $sent): void {
    Native::fakeBridge()->respondTo('Network.Status', ['connected' => false]);

    $screen = Native::visit('/verify-email', platform: 'ios');

    if ($sent) {
        $screen->set('sent', true);
    }

    $screen->assertSee('You’re offline')
        ->assertSee('Try again when you’re connected.')
        ->assertMissingElement('pressable', fn (array $node): bool => in_array($node['ref'] ?? null, ['verify-email-send', 'verify-email-send-again', 'verify-email-open-mail', 'verify-email-check', 'verify-email-change'], true))
        ->assertElement('pressable', pressable('verify-email-log-out'))
        ->call('send')
        ->assertSet('problem', 'offline');

    Saloon::assertNothingSent();
})->with(['default' => false, 'sent' => true]);

it('changes the email from a sheet and shows the sent state for the new address', function (): void {
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()]),
        UpdateProfileRequest::class => MockResponse::make(['data' => unverifiedUser(['email' => 'andy@example.com'])]),
    ]);

    Native::visit('/verify-email')
        ->tap('verify-email-change')
        ->assertSet('changing', true)
        ->assertSet('newEmail', 'andy@swick.family')
        ->assertSee('Change email')
        ->assertSee('We’ll send a new verification link to this address.')
        ->input('verify-email-new-email', 'andy@example.com')
        ->tap('verify-email-change-save')
        ->assertSet('changing', false)
        ->assertSet('sent', true)
        ->assertSee('andy@example.com')
        ->assertSee('Email sent just now');

    Saloon::assertSent(fn ($request): bool => $request instanceof UpdateProfileRequest
        && $request->body()->all() === ['name' => 'Andy Swick', 'email' => 'andy@example.com']);
});

it('keeps the sheet open with the server’s reason when the new email is refused', function (): void {
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()]),
        UpdateProfileRequest::class => MockResponse::make(['errors' => ['email' => ['The email has already been taken.']]], 422),
    ]);

    Native::visit('/verify-email')
        ->tap('verify-email-change')
        ->input('verify-email-new-email', 'taken@example.com')
        ->tap('verify-email-change-save')
        ->assertSet('changing', true)
        ->assertSet('newEmailError', 'The email has already been taken.')
        ->assertSet('email', 'andy@swick.family');
});

it('closes the sheet without asking the server when the email is unchanged', function (): void {
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()])]);

    Native::visit('/verify-email')
        ->tap('verify-email-change')
        ->tap('verify-email-change-save')
        ->assertSet('changing', false)
        ->assertSet('sent', false);

    Saloon::assertNotSent(UpdateProfileRequest::class);
});

it('asks before logging out', function (): void {
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()])]);

    Native::visit('/verify-email')
        ->tap('verify-email-log-out')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'verify-log-out'
            && $params['title'] === 'Log out?'
            && $params['message'] === 'Recipes, inventory, and routines downloaded to this device will be removed, including changes that haven’t been sent yet.');
});

function sequenceOf(MockResponse ...$responses): Closure
{
    return function () use (&$responses): MockResponse {
        return count($responses) > 1 ? array_shift($responses) : $responses[0];
    };
}
