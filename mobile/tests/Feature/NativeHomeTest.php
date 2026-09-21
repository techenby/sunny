<?php

use Native\Mobile\Testing\Native;

it('introduces Sunny with native components', function () {
    $iconPath = public_path('images/sunny-icon.png');

    expect($iconPath)->toBeFile();

    Native::visit('/')
        ->assertSee('Sunny')
        ->assertSee('Welcome home')
        ->assertSee('Your household, organized.')
        ->assertSee('Sunny helps your family collaborate on recipes and keep track of what’s in storage, all in one place.')
        ->assertSee('Recipes')
        ->assertSee('Inventory')
        ->assertSee('Teams')
        ->assertSee('Log in')
        ->assertSee('Register')
        ->assertMissingElement('top_bar')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'welcome-screen'
            && ($node['layout']['width'] ?? null) === 'fill'
            && ($node['layout']['height'] ?? null) === 'fill'
            && ($node['layout']['safe_area'] ?? null) === 1)
        ->assertElement('image', fn (array $node): bool => ($node['ref'] ?? null) === 'sunny-brand-icon'
            && ($node['props']['src'] ?? null) === $iconPath
            && ($node['props']['alt'] ?? null) === 'Sunny'
            && ($node['layout']['width'] ?? null) === 44.0
            && ($node['layout']['height'] ?? null) === 44.0
            && ($node['props']['fit'] ?? null) === 1)
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'sunny-introduction'
            && ($node['layout']['width'] ?? null) === 'fill'
            && ($node['layout']['flex_grow'] ?? null) === 1.0)
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'auth-actions'
            && ($node['layout']['width'] ?? null) === 'fill');
});

it('renders a platform icon for every feature card', function (string $platform, array $iconNames) {
    $screen = Native::visit('/', platform: $platform);

    foreach ($iconNames as $iconName) {
        $screen->assertElement('icon', fn (array $node): bool => ($node['props']['name'] ?? null) === $iconName);
    }
})->with([
    'ios' => ['ios', ['book.pages', 'archivebox', 'person.3']],
    'android' => ['android', ['menu_book', 'inventory_2', 'groups']],
]);

it('takes every color from the native-ui theme tokens', function () {
    $light = config('native-ui.theme.light');
    $dark = config('native-ui.theme.dark');

    Native::visit('/')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'welcome-screen'
            && ($node['style']['bg_color'] ?? null) === $light['background']
            && ($node['props']['dark_bg_color'] ?? null) === $dark['background'])
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'welcome-title'
            && ($node['props']['color'] ?? null) === $light['on-background']
            && ($node['props']['dark_color'] ?? null) === $dark['on-background'])
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'welcome-subtitle'
            && ($node['props']['color'] ?? null) === $light['on-surface-variant']
            && ($node['props']['dark_color'] ?? null) === $dark['on-surface-variant']);
});

it('uses the Sunset palette and typography', function () {
    expect(config('native-ui.theme.light'))
        ->toMatchArray([
            'primary' => '#E85A48',
            'surface' => '#FEF9F7',
            'background' => '#FAF1EF',
        ])
        ->and(config('native-ui.theme.dark'))
        ->toMatchArray([
            'primary' => '#F87966',
            'surface' => '#1A1413',
            'background' => '#0C0807',
        ])
        ->and(config('native-ui.fonts'))
        ->toMatchArray([
            'default' => 'Nunito-Regular',
            'accent' => 'Nunito-Bold',
            'mono' => 'UbuntuMono-Regular',
        ]);

    foreach (config('native-ui.fonts') as $font) {
        expect(resource_path("fonts/{$font}.ttf"))->toBeFile();
    }
});

it('provides accessible authentication actions', function () {
    $screen = Native::visit('/');

    foreach ([
        'login-button' => ['Log in', 'Opens the Sunny login page', 'secondary'],
        'register-button' => ['Register', 'Opens the Sunny registration page', 'primary'],
    ] as $ref => [$label, $hint, $variant]) {
        $screen->assertElement('button', fn (array $node): bool => ($node['ref'] ?? null) === $ref
            && ($node['props']['label'] ?? null) === $label
            && ($node['props']['a11y_label'] ?? null) === $label
            && ($node['props']['a11y_hint'] ?? null) === $hint
            && ($node['props']['variant'] ?? null) === $variant
            && ($node['props']['size'] ?? null) === 'lg');
    }

    $screen->assertAccessible();

    expect($screen->accessibilityViolations())->toBe([]);
});

it('opens the Sunny authentication pages in the native browser', function () {
    $bridge = Native::fakeBridge()
        ->respondTo('Browser.OpenInApp', ['success' => true]);

    Native::visit('/')
        ->tap('login-button')
        ->tap('register-button');

    $bridge
        ->assertCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://sunnyhome.app/login')
        ->assertCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://sunnyhome.app/register')
        ->assertCalledTimes('Browser.OpenInApp', 2)
        ->assertCallOrder([
            'Browser.OpenInApp',
            'Browser.OpenInApp',
        ]);
});
