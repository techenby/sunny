<?php

use App\Http\Integrations\Sunny\Requests\DeleteAccountRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\PendingWrite;
use App\Models\Recipe;
use Native\Mobile\Testing\Native;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function (): void {
    seedSunnyData();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token'])
        ->respondTo('SecureStorage.Delete', ['success' => true]);
});

it('deletes the account, forgets everything on the phone and returns to the welcome screen', function (): void {
    Saloon::fake([DeleteAccountRequest::class => MockResponse::make([], 204)]);
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Unsent recipe']);

    Native::visit('/preferences/delete-account', platform: 'ios')
        ->assertNavTitle('Delete account')
        ->assertSee('It can’t be undone.')
        ->assertAccessible()
        ->input('delete-account-password', 'secret')
        ->tap('delete-account-submit')
        ->assertSet('password', '')
        ->assertReplacedWith('/');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof DeleteAccountRequest
        && $request->body()->all() === ['password' => 'secret']);
    Native::fakeBridge()->assertCalled('SecureStorage.Delete');

    expect(Recipe::count())->toBe(0)
        ->and(PendingWrite::count())->toBe(0)
        ->and(app(SunnyStore::class)->lastSyncedAt())->toBeNull();
});

it('asks for the password before contacting Sunny', function (): void {
    Saloon::fake([DeleteAccountRequest::class => MockResponse::make([], 204)]);

    Native::visit('/preferences/delete-account')
        ->tap('delete-account-submit')
        ->assertSee('Enter your password to delete your account.')
        ->assertNoNavigation();

    Saloon::assertNothingSent();
});

it('keeps the account and its data when Sunny refuses', function (int $status, array $body, string $message): void {
    Saloon::fake([DeleteAccountRequest::class => MockResponse::make($body, $status)]);

    Native::visit('/preferences/delete-account')
        ->input('delete-account-password', 'secret')
        ->tap('delete-account-submit')
        ->assertSee($message)
        ->assertSet('password', '')
        ->assertNoNavigation();

    Native::fakeBridge()->assertNotCalled('SecureStorage.Delete');

    expect(Recipe::count())->toBeGreaterThan(0);
})->with([
    'wrong password' => [422, ['errors' => ['password' => ['The password is incorrect.']]], 'The password is incorrect.'],
    'shared team' => [422, ['errors' => ['teams' => ['Other people still use Family.']]], 'Other people still use Family.'],
    'rate limited' => [429, [], 'Too many attempts. Please wait before trying again.'],
    'server error' => [500, [], 'Sunny is unavailable right now. Your account was not deleted.'],
]);

it('returns to the welcome screen when the saved login has already expired', function (): void {
    Saloon::fake([DeleteAccountRequest::class => MockResponse::make([], 401)]);

    Native::visit('/preferences/delete-account')
        ->input('delete-account-password', 'secret')
        ->tap('delete-account-submit')
        ->assertReplacedWith('/');

    Native::fakeBridge()->assertCalled('SecureStorage.Delete');
});
