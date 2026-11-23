<?php

use App\Http\Integrations\Sunny\Requests\SendPasswordResetLinkRequest;
use App\NativeComponents\ForgotPassword;
use App\NativeComponents\Login;
use Native\Mobile\Testing\Native;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;

const RESET_LINK_SENT = 'If that email has an account, we’ve sent it a link to reset the password.';

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => '']);
});

it('opens from log in with the email filled in', function (): void {
    Native::visit('/login')
        ->input('login-email', 'alex@example.com')
        ->tap('login-forgot-password')
        ->assertNavigatedTo('/forgot-password')
        ->follow()
        ->assertScreen(ForgotPassword::class)
        ->assertNavTitle('Forgot password')
        ->assertSet('email', 'alex@example.com')
        ->assertSee('We’ll email a link to choose a new password.')
        ->assertSee('Send link');

    Native::fakeBridge()->assertNothingBrowsed();
});

it('renders accessibly', function (): void {
    Native::visit('/forgot-password', platform: 'ios')->assertAccessible();
});

it('confirms with the server’s message whatever the email', function (): void {
    Saloon::fake([SendPasswordResetLinkRequest::class => MockResponse::make(['message' => RESET_LINK_SENT], 202)]);

    Native::visit('/forgot-password', platform: 'ios')
        ->input('forgot-password-email', ' alex@example.com ')
        ->tap('forgot-password-submit')
        ->assertSee('Check your email')
        ->assertSee('alex@example.com')
        ->assertSee(RESET_LINK_SENT)
        ->assertSee('The link opens the Sunny website, where you can set your new password.')
        ->assertSee('Back to log in')
        ->assertSee('Open Mail')
        ->assertNoNavigation();

    Saloon::assertSent(fn (Request $request): bool => $request instanceof SendPasswordResetLinkRequest
        && $request->body()->all() === ['email' => 'alex@example.com']);
});

it('opens Mail on iOS and leaves the button out on Android', function (): void {
    Saloon::fake([SendPasswordResetLinkRequest::class => MockResponse::make(['message' => RESET_LINK_SENT], 202)]);

    Native::visit('/forgot-password', platform: 'ios')
        ->input('forgot-password-email', 'alex@example.com')
        ->tap('forgot-password-submit')
        ->tap('forgot-password-open-mail');

    Native::fakeBridge()->assertBrowsed('message://');

    Native::visit('/forgot-password', platform: 'android')
        ->input('forgot-password-email', 'alex@example.com')
        ->tap('forgot-password-submit')
        ->assertSee('Back to log in')
        ->assertDontSee('Open Mail');
});

it('goes back to log in from the confirmation', function (): void {
    Saloon::fake([SendPasswordResetLinkRequest::class => MockResponse::make(['message' => RESET_LINK_SENT], 202)]);

    Native::visit('/login')
        ->input('login-email', 'alex@example.com')
        ->tap('login-forgot-password')
        ->follow()
        ->tap('forgot-password-submit')
        ->tap('forgot-password-back')
        ->assertWentBack()
        ->goBack()
        ->assertScreen(Login::class)
        ->assertSet('email', 'alex@example.com');
});

it('goes back from the form to log in', function (): void {
    Native::visit('/login')
        ->tap('login-forgot-password')
        ->follow()
        ->tap('forgot-password-login-link')
        ->assertWentBack();
});

it('shows an invalid email under the field', function (): void {
    Saloon::fake([SendPasswordResetLinkRequest::class => MockResponse::make(['message' => 'The email field must be a valid email address.', 'errors' => ['email' => ['The email field must be a valid email address.']]], 422)]);

    Native::visit('/forgot-password')
        ->input('forgot-password-email', 'alex@example')
        ->tap('forgot-password-submit')
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'forgot-password-email'
            && ($node['props']['is_error'] ?? null) === true
            && ($node['props']['supporting'] ?? null) === 'The email field must be a valid email address.')
        ->assertDontSee('Check your email');
});

it('asks for an email before contacting Sunny', function (): void {
    Saloon::fake([SendPasswordResetLinkRequest::class => MockResponse::make(['message' => RESET_LINK_SENT], 202)]);

    Native::visit('/forgot-password')
        ->tap('forgot-password-submit')
        ->assertSee('Enter your email address.');

    Saloon::assertNothingSent();
});

it('keeps the email when offline and tries again', function (): void {
    Saloon::fake([SendPasswordResetLinkRequest::class => unreachableSunny()]);

    $screen = Native::visit('/forgot-password')
        ->input('forgot-password-email', 'alex@example.com')
        ->tap('forgot-password-submit')
        ->assertSee('You’re offline')
        ->assertSee('Try again when you’re connected. Nothing’s been sent yet.')
        ->assertSee('Try again')
        ->assertSet('email', 'alex@example.com');

    Saloon::fake([SendPasswordResetLinkRequest::class => MockResponse::make(['message' => RESET_LINK_SENT], 202)]);

    $screen->tap('forgot-password-submit')->assertSee('Check your email');
});

it('does not contact Sunny when the phone knows it is offline', function (): void {
    Native::fakeBridge()->respondTo('Network.Status', ['connected' => false]);
    Saloon::fake([SendPasswordResetLinkRequest::class => MockResponse::make(['message' => RESET_LINK_SENT], 202)]);

    $screen = Native::visit('/forgot-password')
        ->assertSee('You’re offline')
        ->assertSee('Try again when you’re connected.')
        ->assertDontSee('Nothing’s been sent yet.')
        ->input('forgot-password-email', 'alex@example.com')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'forgot-password-submit')
        ->call('send')
        ->assertSee('You’re offline');

    Saloon::assertNothingSent();

    $screen->bridge()->respondTo('Network.Status', ['connected' => true]);

    $screen->firePolls()
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'forgot-password-submit');
});

it('asks people to wait after too many tries', function (): void {
    Saloon::fake([SendPasswordResetLinkRequest::class => MockResponse::make(['message' => 'Too Many Attempts.'], 429)]);

    $screen = Native::visit('/forgot-password')
        ->input('forgot-password-email', 'alex@example.com')
        ->tap('forgot-password-submit')
        ->assertSee('Too many tries')
        ->assertSee('Wait a minute and try again. In the meantime, check your inbox for the links we sent.')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'forgot-password-submit')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'forgot-password-submit')
        ->call('send');

    Saloon::assertSentCount(1);

    $this->travel(61)->seconds();

    $screen->firePolls()->assertDontSee('Too many tries');
});

it('says something went wrong after a server error', function (): void {
    Saloon::fake([SendPasswordResetLinkRequest::class => MockResponse::make(['message' => 'Server Error'], 500)]);

    Native::visit('/forgot-password')
        ->input('forgot-password-email', 'alex@example.com')
        ->tap('forgot-password-submit')
        ->assertSee('Something went wrong on our end')
        ->assertSee('No link sent. Try again in a moment.')
        ->assertSee('Send link')
        ->assertSet('sending', false);
});

it('says when password reset is turned off', function (): void {
    Saloon::fake([SendPasswordResetLinkRequest::class => MockResponse::make(['message' => 'Not Found'], 404)]);

    Native::visit('/forgot-password')
        ->input('forgot-password-email', 'alex@example.com')
        ->tap('forgot-password-submit')
        ->assertSee('Password reset isn’t available right now');
});
