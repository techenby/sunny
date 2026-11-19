<?php

use App\Http\Integrations\Sunny\EmailVerificationRequired;
use App\Http\Integrations\Sunny\Requests\GetUserRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\SendVerificationEmailRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Models\PendingWrite;
use Illuminate\Support\Facades\DB;
use Native\Mobile\Testing\Native;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function (): void {
    seedSunnyData();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token'])
        ->respondTo('SecureStorage.Delete', ['success' => true]);
});

function rememberUnverifiedAndy(?string $graceEndsAt): void
{
    DB::table('sunny_accounts')->updateOrInsert(['server' => SunnyStore::server()], [
        'user_id' => 7,
        'name' => 'Andy Swick',
        'email' => 'andy@swick.family',
        'email_verified_at' => null,
        'email_verification_grace_ends_at' => $graceEndsAt,
    ]);
}

function unverifiedUser(string $graceEndsAt): array
{
    return ['id' => 7, 'name' => 'Andy Swick', 'email' => 'andy@swick.family', 'email_verified_at' => null, 'email_verification_grace_ends_at' => $graceEndsAt];
}

it('remembers when Sunny will start asking for a verified email', function (): void {
    rememberUnverifiedAndy(null);

    app(SunnyStore::class)->rememberProfile(unverifiedUser('2027-01-05T05:59:59.000000Z'));

    expect(app(SunnyStore::class)->emailVerificationDueAt()?->toIso8601String())->toBe('2027-01-05T05:59:59+00:00')
        ->and(app(SunnyStore::class)->needsEmailVerification())->toBeFalse();

    $this->travelTo('2027-01-06');

    expect(app(SunnyStore::class)->needsEmailVerification())->toBeTrue();
});

it('reminds an unverified user on Summary before the grace period ends', function (): void {
    $this->travelTo('2026-12-01 12:00:00');
    rememberUnverifiedAndy('2027-01-04 23:59:59');
    Saloon::fake([SyncRequest::class => MockResponse::make([], 503), GetUserRequest::class => MockResponse::make(['data' => unverifiedUser('2027-01-04T23:59:59Z')])]);

    Native::visit('/dashboard')
        ->assertSee('Verify your email by January 4')
        ->tap('dashboard-verify-email')
        ->assertNavigatedTo('/preferences/account');
});

it('does not remind a verified user', function (): void {
    rememberUnverifiedAndy(null);
    DB::table('sunny_accounts')->update(['email_verified_at' => now()]);
    Saloon::fake([SyncRequest::class => MockResponse::make([], 503)]);

    Native::visit('/dashboard')->assertDontSee('Verify your email by');
});

it('opens the verify screen on launch once the grace period is over', function (): void {
    rememberUnverifiedAndy(now()->subDay()->toDateTimeString());

    Native::visit('/')->assertReplacedWith('/verify-email');
});

it('stops syncing and keeps queued changes once Sunny asks for a verified email', function (): void {
    rememberUnverifiedAndy(now()->addDay()->toDateTimeString());
    app(SunnyOutbox::class)->queue('checklists', 1, ['name' => 'Hardware store', 'type' => 'shopping']);
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => unverifiedUser(now()->addDay()->toIso8601String())]),
        SaveRecordRequest::class => MockResponse::make(['message' => 'Verify your email address to keep using SunnyHome.', 'email_verification_required' => true], 423),
    ]);

    Native::visit('/dashboard')
        ->call('sync')
        ->assertReplacedWith('/verify-email');

    expect(PendingWrite::query()->whereNull('error')->count())->toBe(1)
        ->and(app(SunnyStore::class)->needsEmailVerification())->toBeTrue();
    Saloon::assertNotSent(SyncRequest::class);
});

it('checks with Sunny before syncing an account whose grace period is over', function (): void {
    rememberUnverifiedAndy(now()->addDay()->toDateTimeString());
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => unverifiedUser(now()->subMinute()->toIso8601String())])]);

    expect(fn () => app(SunnySyncCoordinator::class)->sync('saved-token'))->toThrow(EmailVerificationRequired::class);

    Saloon::assertSent(GetUserRequest::class);
    Saloon::assertNotSent(SyncRequest::class);
});

it('sends a verification link from the verify screen', function (): void {
    rememberUnverifiedAndy(now()->subDay()->toDateTimeString());
    Saloon::fake([SendVerificationEmailRequest::class => MockResponse::make([], 202)]);

    Native::visit('/verify-email')
        ->assertSee('Confirm andy@swick.family is yours to keep using Sunny.')
        ->assertAccessible()
        ->tap('verify-email-send')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Verification email sent to andy@swick.family')
        ->assertSee('Send it again');
});

it('says so when the email still is not verified', function (): void {
    rememberUnverifiedAndy(now()->subDay()->toDateTimeString());
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => unverifiedUser(now()->subDay()->toIso8601String())])]);

    Native::visit('/verify-email')
        ->tap('verify-email-check')
        ->assertSee('Your email isn’t verified yet. Open the link we sent to andy@swick.family, then try again.');
});

it('goes back to Summary once the email is verified', function (): void {
    rememberUnverifiedAndy(now()->subDay()->toDateTimeString());
    Saloon::fake([
        GetUserRequest::class => MockResponse::make(['data' => [...unverifiedUser(now()->subDay()->toIso8601String()), 'email_verified_at' => now()->toIso8601String(), 'email_verification_grace_ends_at' => null]]),
        SyncRequest::class => MockResponse::make([], 503),
    ]);

    Native::visit('/verify-email')
        ->tap('verify-email-check')
        ->assertReplacedWith('/dashboard');

    expect(app(SunnyStore::class)->needsEmailVerification())->toBeFalse();
});

it('lets an unverified user change their email from the verify screen', function (): void {
    rememberUnverifiedAndy(now()->subDay()->toDateTimeString());

    Native::visit('/verify-email')
        ->tap('verify-email-change')
        ->assertNavigatedTo('/preferences/account');
});
