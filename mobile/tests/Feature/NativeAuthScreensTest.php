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
        ->assertSee('Remember me')
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
        ->check('login-remember')
        ->assertSet('email', 'andy@example.com')
        ->assertSet('password', 'secret-password')
        ->assertSet('remember', true);
});

it('renders the register screen', function () {
    Native::visit('/register')
        ->assertScreen(Register::class)
        ->assertNavTitle('Create account')
        ->assertSee('Enter your details below to create your account.')
        ->assertSee('Name')
        ->assertSee('Email address')
        ->assertSee('Confirm password')
        ->assertSee('Create account')
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'register-password'
            && ($node['props']['secure'] ?? null) === true)
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'register-password-confirmation'
            && ($node['props']['secure'] ?? null) === true)
        ->assertAccessible();
});

it('binds the register fields', function () {
    Native::visit('/register')
        ->input('register-name', 'Andy Swick')
        ->input('register-email', 'andy@example.com')
        ->input('register-password', 'secret-password')
        ->input('register-password-confirmation', 'secret-password')
        ->assertSet('name', 'Andy Swick')
        ->assertSet('email', 'andy@example.com')
        ->assertSet('password', 'secret-password')
        ->assertSet('passwordConfirmation', 'secret-password');
});

it('switches between the login and register screens without stacking them', function (string $from, string $link, string $to) {
    Native::visit($from)
        ->tap($link)
        ->assertReplacedWith($to);
})->with([
    'login to register' => ['/login', 'login-register-link', '/register'],
    'register to login' => ['/register', 'register-login-link', '/login'],
]);

it('replaces the form with the dashboard on submit until authentication is integrated', function (string $uri, string $button) {
    Native::visit($uri)
        ->tap($button)
        ->assertReplacedWith('/dashboard');
})->with([
    'login' => ['/login', 'login-submit'],
    'register' => ['/register', 'register-submit'],
]);
