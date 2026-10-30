<?php

use App\Models\User;

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
        ->assertSee('Sunny Home')
        ->assertSee('Built by Andy Swick')
        ->assertSee('https://github.com/techenby/sunny')
        ->assertSee(route('privacy'))
        ->assertSee(route('terms'));
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
        ->assertSee('"@type":"WebSite","name":"Sunny Home"', false)
        ->assertSee('"@type":"WebApplication","name":"Sunny Home"', false)
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
        ->assertSee(route('terms'));
});

test('displays the terms of service', function () {
    $this->get(route('terms'))
        ->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee(route('privacy'));
});
