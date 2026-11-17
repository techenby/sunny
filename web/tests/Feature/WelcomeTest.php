<?php

use App\Models\User;
use Illuminate\Support\Js;

test('displays the landing page for guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(asset('icon.svg'))
        ->assertSee('Log in')
        ->assertSee('Register')
        ->assertSee('Get started')
        ->assertSee('Your household, organized')
        ->assertSeeInOrder(['id="features"', 'id="kiosk"', 'id="mobile"', 'id="assistant"', 'id="built-by"'], false)
        ->assertDontSee('Soon')
        ->assertSee('SunnyHome')
        ->assertSee('Built by Andy Swick')
        ->assertSee('https://github.com/techenby/sunny')
        ->assertSee(route('privacy'))
        ->assertSee(route('terms'))
        ->assertSee(route('delete-account'));
});

test('includes a description and preview image for link previews', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('<title>' . config('app.name') . '</title>', false)
        ->assertSee('<meta name="description" content="Recipes, lists, routines, calendars, and everything in storage', false)
        ->assertSee('<meta property="og:title" content="' . config('app.name') . '">', false)
        ->assertSee('<meta property="og:image" content="' . asset('images/og-image.png') . '">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);

    expect(public_path('images/og-image.png'))->toBeFile();
});

test('describes the app with structured data', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('"@type":"WebSite","name":"SunnyHome"', false)
        ->assertSee('"@type":"WebApplication","name":"SunnyHome"', false)
        ->assertSee('"offers":{"@type":"Offer","price":0,"priceCurrency":"USD"}', false);
});

test('lets visitors switch between kiosk preview tabs', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee("tab = 'calendar'", false)
        ->assertSee("tab = 'routines'", false)
        ->assertSee("tab = 'lists'", false)
        ->assertSee("tab = 'meals'", false)
        ->assertSee('Piano lesson')
        ->assertSee('Weekend chores')
        ->assertSee('Birthday wishlist')
        ->assertSee('Meal plans and dinner ideas will live here.');
});

test('shows dashboard link for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Open your dashboard')
        ->assertDontSee('Get started');
});

test('displays the privacy policy', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('<title>Privacy Policy - ' . config('app.name') . '</title>', false)
        ->assertSee('Privacy Policy')
        ->assertSee('Crash reports are kept by NativeWatch for up to 90 days.')
        ->assertSee('is not directed to children under 13')
        ->assertSee(route('delete-account'))
        ->assertSee(route('terms'));
});

test('explains how to delete an account to guests', function () {
    $this->get(route('delete-account'))
        ->assertOk()
        ->assertSee('<title>Delete your account - ' . config('app.name') . '</title>', false)
        ->assertSee('SunnyHome')
        ->assertSee('TechEnby')
        ->assertSeeInOrder(['Preferences', 'Delete account', 'Delete my account'])
        ->assertSee(route('profile.edit'))
        ->assertSee('mailto:' . config('mail.from.address'), false)
        ->assertSee('If you own a team other people use')
        ->assertSee('Make owner')
        ->assertSee('What we delete')
        ->assertSee('What we keep')
        ->assertSee('NativeWatch keeps them for up to 90 days')
        ->assertSee(route('privacy'));
});

test('displays the terms of service', function () {
    $this->get(route('terms'))
        ->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee(route('delete-account'))
        ->assertSee(route('privacy'));
});

test('loads bento in production when it is configured', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['bentonow.site_uuid' => 'site-uuid']);

    $this->get('/')
        ->assertOk()
        ->assertSee('<script src="https://app.bentonow.com/site-uuid.js" defer></script>', false);
});

test('leaves bento out in production when it is not configured', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['bentonow.site_uuid' => null]);

    $this->get('/')
        ->assertOk()
        ->assertDontSee('https://app.bentonow.com/', false);
});

test('identifies signed-in users to bento in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['bentonow.site_uuid' => 'site-uuid']);
    $user = User::factory()->create(['email' => "o'brien@example.com"]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('bento.identify(' . Js::from($user->email) . ');', false);
});
