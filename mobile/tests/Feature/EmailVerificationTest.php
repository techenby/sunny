<?php

use App\Http\Integrations\Sunny\EmailVerificationRequired;
use App\Http\Integrations\Sunny\Requests\CreateTokenRequest;
use App\Http\Integrations\Sunny\Requests\GetUserRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\SendVerificationEmailRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
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

it('sends a verification link from the verify screen', function (): void {
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()]),
        SendVerificationEmailRequest::class => MockResponse::make([], 202),
    ]);

    Native::visit('/verify-email')
        ->assertSee('Confirm andy@swick.family is yours to keep using Sunny.')
        ->assertAccessible()
        ->tap('verify-email-send')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Verification email sent to andy@swick.family')
        ->assertSee('Send it again');
});

it('says so when the email still is not verified', function (): void {
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()])]);

    Native::visit('/verify-email')
        ->tap('verify-email-check')
        ->assertSee('Your email isn’t verified yet. Open the link we sent to andy@swick.family, then try again.');
});

it('carries on once the email is verified', function (): void {
    $responses = [MockResponse::make(['data' => unverifiedUser()]), MockResponse::make(['data' => unverifiedUser(['email_verified_at' => now()->toIso8601String()])])];
    Saloon::fake([GetUserRequest::class => function () use (&$responses): MockResponse {
        return array_shift($responses) ?? MockResponse::make(['data' => unverifiedUser(['email_verified_at' => now()->toIso8601String()])]);
    }]);

    Native::visit('/verify-email')
        ->tap('verify-email-check')
        ->assertReplacedWith('/');

    expect(app(SunnyStore::class)->needsEmailVerification())->toBeFalse();
});

it('moves on by itself when the email was verified on the web', function (): void {
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => unverifiedUser(['email_verified_at' => now()->toIso8601String()])])]);

    Native::visit('/verify-email')->assertReplacedWith('/');
});

it('lets an unverified user change their email from the verify screen', function (): void {
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => unverifiedUser()])]);

    Native::visit('/verify-email')
        ->tap('verify-email-change')
        ->assertNavigatedTo('/preferences/account');
});

it('signs out from the verify screen when the server no longer accepts the saved login', function (string $button, array $responses): void {
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Soup'], 1);
    Saloon::fake($responses);

    Native::visit('/verify-email')
        ->assertNoNavigation()
        ->tap($button)
        ->assertReplacedWith('/')
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

    Native::visit('/verify-email')->assertReplacedWith('/');

    Native::fakeBridge()->assertCalled('SecureStorage.Delete');
    expect(PendingWrite::count())->toBe(1)->and(app(SunnyStore::class)->rememberedAccount())->toBe(7);
});

it('sends changes queued before a revoked login once the same person signs back in', function (): void {
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Soup'], 1);
    Saloon::fake([GetUserRequest::class => MockResponse::make([], 401)]);
    Native::visit('/verify-email')->assertReplacedWith('/');

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
    Native::visit('/verify-email')->assertReplacedWith('/');

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

function sequenceOf(MockResponse ...$responses): Closure
{
    return function () use (&$responses): MockResponse {
        return count($responses) > 1 ? array_shift($responses) : $responses[0];
    };
}
