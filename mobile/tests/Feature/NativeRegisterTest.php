<?php

use App\Http\Integrations\Sunny\Requests\GetUserRequest;
use App\Http\Integrations\Sunny\Requests\RegisterRequest;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\Onboarding;
use App\NativeComponents\Login;
use App\NativeComponents\Register;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function (): void {
    fakeSecureStorage();
});

function fillRegistration(string $confirmation = 'Correct-Horse-9!'): TestableComponent
{
    return Native::visit('/register')
        ->input('register-name', 'Alex Rivera')
        ->input('register-email', 'alex@example.com')
        ->input('register-password', 'Correct-Horse-9!')
        ->input('register-password-confirmation', $confirmation);
}

/**
 * @return array<string, mixed>
 */
function registeredUser(array $attributes = []): array
{
    return ['id' => 42, 'name' => 'Alex Rivera', 'email' => 'alex@example.com', 'email_verified_at' => null, 'onboarded_at' => null, 'token' => 'new-token', ...$attributes];
}

function verifiedRegistrationProfile(): MockResponse
{
    return MockResponse::make(['data' => [
        'id' => 42, 'name' => 'Alex Rivera', 'email' => 'alex@example.com', 'email_verified_at' => '2026-11-19T00:00:00.000000Z',
    ]]);
}

it('renders the native sign-up form', function (): void {
    Native::visit('/register', platform: 'ios')
        ->assertScreen(Register::class)
        ->assertNavTitle('Create account')
        ->assertSee('Enter your details below to create your account.')
        ->assertSee('At least 12 characters, with upper and lower case letters, a number and a symbol. Passwords found in known data leaks can’t be used.')
        ->assertSee('Already have an account?')
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'register-password'
            && ($node['props']['secure'] ?? null) === true
            && ($node['props']['revealable'] ?? null) === true)
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'register-password-confirmation'
            && ($node['props']['secure'] ?? null) === true
            && ($node['props']['revealable'] ?? null) === true)
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'register-terms')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'register-privacy')
        ->assertAccessible();
});

it('creates the account, signs in and waits for verification before setup', function (): void {
    Saloon::fake([
        RegisterRequest::class => MockResponse::make(registeredUser(), 201),
        GetUserRequest::class => MockResponse::make(['data' => ['id' => 42]]),
    ]);

    $home = fillRegistration()
        ->tap('register-submit')
        ->assertSet('password', '')
        ->assertSet('passwordConfirmation', '')
        ->assertReplacedWith('/')
        ->follow()
        ->assertReplacedWith('/verify-email');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof RegisterRequest && $request->body()->all() === [
        'name' => 'Alex Rivera',
        'email' => 'alex@example.com',
        'password' => 'Correct-Horse-9!',
        'password_confirmation' => 'Correct-Horse-9!',
        'device_name' => 'Sunny Mobile',
    ]);
    Native::fakeBridge()->assertCalled('SecureStorage.Set', fn (array $params): bool => $params['value'] === 'new-token');
    Native::fakeBridge()->assertNothingBrowsed();
    expect(Onboarding::current())->first_name->toBe('Alex')
        ->and(app(SunnyStore::class)->profile())->toMatchArray(['name' => 'Alex Rivera', 'email' => 'alex@example.com']);

    Saloon::fake([GetUserRequest::class => verifiedRegistrationProfile()]);
    $home->follow()->tap('account-check-verification')->follow()->assertReplacedWith('/setup');
});

it('sends an invited account to the dashboard after verification', function (): void {
    Saloon::fake([
        RegisterRequest::class => MockResponse::make(registeredUser(['onboarded_at' => '2026-11-17T00:00:00.000000Z']), 201),
        GetUserRequest::class => MockResponse::make(['data' => ['id' => 42]]),
    ]);

    $home = fillRegistration()
        ->tap('register-submit')
        ->assertReplacedWith('/')
        ->follow()
        ->assertReplacedWith('/verify-email');

    expect(Onboarding::current())->toBeNull();

    Saloon::fake([GetUserRequest::class => verifiedRegistrationProfile()]);
    $home->follow()->tap('account-check-verification')->follow()->assertReplacedWith('/dashboard');
});

it('shows each validation error under its field', function (string $field, string $ref, string $message): void {
    Saloon::fake([RegisterRequest::class => MockResponse::make(['message' => $message, 'errors' => [$field => [$message]]], 422)]);

    fillRegistration()
        ->tap('register-submit')
        ->assertSee($message)
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === $ref
            && ($node['props']['is_error'] ?? null) === true
            && ($node['props']['supporting'] ?? null) === $message)
        ->assertSet('password', 'Correct-Horse-9!')
        ->assertSet('saving', false)
        ->assertNoNavigation();

    Native::fakeBridge()->assertNotCalled('SecureStorage.Set');
})->with([
    'name' => ['name', 'register-name', 'The name field must not be greater than 255 characters.'],
    'email' => ['email', 'register-email', 'The email field must be a valid email address.'],
    'password' => ['password', 'register-password', 'The password field must contain at least one symbol.'],
    'confirmation' => ['password', 'register-password', 'The password field confirmation does not match.'],
]);

it('shows every password problem at once above the rule', function (): void {
    Saloon::fake([RegisterRequest::class => MockResponse::make(['errors' => ['password' => [
        'The password field must be at least 12 characters.',
        'The password field must contain at least one symbol.',
    ]]], 422)]);

    fillRegistration()
        ->tap('register-submit')
        ->assertSee('The password field must be at least 12 characters. The password field must contain at least one symbol.')
        ->assertSee('Passwords found in known data leaks can’t be used.');
});

it('offers to log in instead when the email already has an account', function (): void {
    Saloon::fake([RegisterRequest::class => MockResponse::make(['errors' => ['email' => ['The email has already been taken.']]], 422)]);

    fillRegistration()
        ->tap('register-submit')
        ->assertSee('An account already uses this email.')
        ->assertSee('Log in instead')
        ->tap('register-login-instead')
        ->assertNavigatedTo('/login')
        ->follow()
        ->assertScreen(Login::class)
        ->assertSet('email', 'alex@example.com');
});

it('goes back to log in with the email when it came from there', function (): void {
    Saloon::fake([RegisterRequest::class => MockResponse::make(['errors' => ['email' => ['The email has already been taken.']]], 422)]);

    Native::visit('/login')
        ->tap('login-register-link')
        ->follow()
        ->input('register-name', 'Alex Rivera')
        ->input('register-email', 'alex@example.com')
        ->input('register-password', 'Correct-Horse-9!')
        ->input('register-password-confirmation', 'Correct-Horse-9!')
        ->tap('register-submit')
        ->tap('register-login-instead')
        ->assertWentBack()
        ->goBack()
        ->assertScreen(Login::class)
        ->assertSet('email', 'alex@example.com');
});

it('checks the form before contacting Sunny', function (): void {
    Saloon::fake([RegisterRequest::class => MockResponse::make(registeredUser(), 201)]);

    Native::visit('/register')
        ->tap('register-submit')
        ->assertSee('Enter your name.')
        ->assertSee('Enter your email address.')
        ->assertSee('Choose a password.');

    fillRegistration('Something-Else-1!')
        ->tap('register-submit')
        ->assertSee('The passwords don’t match.')
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'register-password-confirmation'
            && ($node['props']['is_error'] ?? null) === true)
        ->assertNoNavigation();

    Saloon::assertNothingSent();
});

it('keeps what was typed when offline and tries again', function (): void {
    Saloon::fake([
        RegisterRequest::class => unreachableSunny(),
        GetUserRequest::class => MockResponse::make(['data' => ['id' => 42]]),
    ]);

    $screen = fillRegistration()
        ->tap('register-submit')
        ->assertSee('You’re offline')
        ->assertSee('Creating an account needs a connection. Nothing’s been sent yet.')
        ->assertSee('Try again')
        ->assertSet('email', 'alex@example.com')
        ->assertSet('password', 'Correct-Horse-9!')
        ->assertNoNavigation();

    Saloon::fake([RegisterRequest::class => MockResponse::make(registeredUser(), 201)]);

    $screen->tap('register-submit')
        ->assertSet('failure', null)
        ->assertReplacedWith('/');
});

it('does not contact Sunny when the phone knows it is offline', function (): void {
    Native::fakeBridge()->respondTo('Network.Status', ['connected' => false]);
    Saloon::fake([RegisterRequest::class => MockResponse::make(registeredUser(), 201)]);

    fillRegistration()
        ->tap('register-submit')
        ->assertSet('failure', 'offline')
        ->assertSee('Try again');

    Saloon::assertNothingSent();
});

it('offers to try again after a server error', function (int $status): void {
    Saloon::fake([RegisterRequest::class => MockResponse::make(['message' => 'Server Error'], $status)]);

    fillRegistration()
        ->tap('register-submit')
        ->assertSee('Something went wrong on our end')
        ->assertSee('No account was created. Try again in a moment.')
        ->assertSee('Try again')
        ->assertSet('saving', false)
        ->assertNoNavigation();
})->with([500, 503]);

it('asks people to wait after too many tries', function (): void {
    Saloon::fake([RegisterRequest::class => MockResponse::make(['message' => 'Too Many Attempts.'], 429)]);

    $screen = fillRegistration()
        ->tap('register-submit')
        ->assertSee('Too many tries')
        ->assertSee('Wait a minute and try again.')
        ->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'register-submit' && ($node['props']['disabled'] ?? null) === true)
        ->tap('register-submit');

    Saloon::assertSentCount(1);

    $this->travel(61)->seconds();

    $screen->firePolls()
        ->assertDontSee('Too many tries')
        ->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'register-submit' && ($node['props']['disabled'] ?? false) === false);
});

it('says when sign-up is turned off', function (): void {
    Saloon::fake([RegisterRequest::class => MockResponse::make(['message' => 'Not Found'], 404)]);

    fillRegistration()
        ->tap('register-submit')
        ->assertSee('Sign-up isn’t available right now')
        ->assertNoNavigation();
});

it('asks people to log in when the new token cannot be saved', function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Set', ['success' => false]);
    Saloon::fake([RegisterRequest::class => MockResponse::make(registeredUser(), 201)]);

    fillRegistration()
        ->tap('register-submit')
        ->assertSee('Your account was created, but Sunny couldn’t sign you in. Log in to continue.')
        ->assertNoNavigation();
});

it('shows the button as working while the account is created', function (): void {
    Native::visit('/register')
        ->set('saving', true)
        ->assertSee('Creating account…')
        ->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'register-submit' && ($node['props']['disabled'] ?? null) === true)
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'register-email' && ($node['props']['disabled'] ?? null) === true);
});

it('opens the terms and privacy policy in the in-app browser', function (string $ref, string $url): void {
    Native::visit('/register')->tap($ref)->assertNoNavigation();

    Native::fakeBridge()->assertOpenedInApp($url);
})->with([
    'terms' => ['register-terms', 'https://sunny.example/terms'],
    'privacy' => ['register-privacy', 'https://sunny.example/privacy'],
]);
