<?php

use App\NativeComponents\Login;
use App\NativeComponents\Register;
use Native\Mobile\Testing\Native;

it('renders the login screen', function () {
    Native::visit('/login')
        ->assertScreen(Login::class)
        ->assertNavTitle('Log in')
        ->assertSee('Enter your email and password below to log in.')
        ->assertSee('Email address')
        ->assertSee('Password')
        ->assertSee('Log in')
        ->assertSee('Sign up')
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'login-password'
            && ($node['props']['secure'] ?? null) === true)
        ->assertAccessible();
});

it('binds the login fields', function () {
    Native::visit('/login')
        ->input('login-email', 'andy@example.com')
        ->input('login-password', 'secret-password')
        ->assertSet('email', 'andy@example.com')
        ->assertSet('password', 'secret-password');
});

it('switches between the login and register screens without stacking them', function (string $from, string $link, string $to, string $returnLink, string $screen) {
    Native::visit($from)
        ->tap($link)
        ->assertNavigatedTo($to)
        ->follow()
        ->tap($returnLink)
        ->assertWentBack()
        ->goBack()
        ->assertScreen($screen);
})->with([
    'login to register' => ['/login', 'login-register-link', '/register', 'register-login-link', Login::class],
    'register to login' => ['/register', 'register-login-link', '/login', 'login-register-link', Register::class],
]);

it('validates login before contacting the API', function () {
    Native::visit('/login')->tap('login-submit')->assertSee('The email field is required.')->assertNoNavigation();
});
