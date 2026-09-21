<?php

use Native\Mobile\Testing\Native;

it('renders the welcome screen with native components', function () {
    $logoPath = public_path('images/nativephp-logo.png');

    expect($logoPath)->toBeFile();

    Native::visit('/')
        ->assertSee('Your app is ready.')
        ->assertSee('Read the Docs')
        ->assertSee('Join the Community')
        ->assertSee('Explore on GitHub')
        ->assertSee('Built on NativePHP · Made by Bifrost')
        ->assertSee('Powered by Laravel')
        ->assertMissingElement('top_bar')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'welcome-screen'
            && ($node['layout']['width'] ?? null) === 'fill'
            && ($node['layout']['height'] ?? null) === 'fill'
            && ($node['layout']['align_items'] ?? null) === 1
            && ($node['layout']['justify_content'] ?? null) === 1
            && ($node['layout']['safe_area'] ?? null) === 1)
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'welcome-card'
            && ($node['style']['border_radius'] ?? null) === 16.0)
        ->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'nativephp-logo-container'
            && ($node['layout']['width'] ?? null) === 'fill'
            && ($node['layout']['justify_content'] ?? null) === 1)
        ->assertElement('image', fn (array $node): bool => ($node['ref'] ?? null) === 'nativephp-logo'
            && ($node['props']['src'] ?? null) === $logoPath
            && ($node['layout']['width'] ?? null) === 64.0
            && ($node['layout']['height'] ?? null) === 64.0
            && ($node['props']['fit'] ?? null) === 1)
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'welcome-footer'
            && ($node['layout']['width'] ?? null) === 'fill'
            && ($node['layout']['align_items'] ?? null) === 1);
});

it('takes every color from the native-ui theme tokens', function () {
    $light = config('native-ui.theme.light');
    $dark = config('native-ui.theme.dark');

    $screen = Native::visit('/')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'welcome-screen'
            && ($node['style']['bg_color'] ?? null) === $light['background']
            && ($node['props']['dark_bg_color'] ?? null) === $dark['background'])
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'welcome-card'
            && ($node['style']['bg_color'] ?? null) === $light['surface']
            && ($node['props']['dark_bg_color'] ?? null) === $dark['surface'])
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'welcome-title'
            && ($node['props']['color'] ?? null) === $light['primary']
            && ($node['props']['dark_color'] ?? null) === $dark['primary'])
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'welcome-subtitle'
            && ($node['props']['color'] ?? null) === $light['on-surface-variant']
            && ($node['props']['dark_color'] ?? null) === $dark['on-surface-variant']);

    foreach (['docs-link', 'community-link', 'github-link'] as $ref) {
        $screen->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === $ref
            && ($node['style']['bg_color'] ?? null) === $light['surface-variant']
            && ($node['style']['border_color'] ?? null) === $light['outline']
            && ($node['props']['dark_bg_color'] ?? null) === $dark['surface-variant']
            && ($node['props']['dark_border_color'] ?? null) === $dark['outline']);
    }
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

it('is fully accessible', function () {
    $links = [
        'docs-link' => ['Read the Docs', 'Opens the documentation in an in-app browser'],
        'community-link' => ['Join the Community', 'Opens the Discord invite in your browser'],
        'github-link' => ['Explore on GitHub', 'Opens the GitHub organization in your browser'],
    ];

    $screen = Native::visit('/')
        ->assertElement('image', fn (array $node): bool => ($node['ref'] ?? null) === 'nativephp-logo'
            && ($node['props']['alt'] ?? null) === 'NativePHP');

    foreach ($links as $ref => [$label, $hint]) {
        $screen->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === $ref
            && ($node['props']['a11y_label'] ?? null) === $label
            && ($node['props']['a11y_hint'] ?? null) === $hint);
    }

    $screen->assertAccessible();

    expect($screen->accessibilityViolations())->toBe([]);
});

it('opens every welcome link through the native browser bridge', function () {
    $bridge = Native::fakeBridge()
        ->respondTo('Browser.OpenInApp', ['success' => true])
        ->respondTo('Browser.Open', ['success' => true]);

    Native::visit('/')
        ->tap('docs-link')
        ->tap('community-link')
        ->tap('github-link');

    $bridge
        ->assertCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://nativephp.com/docs/mobile')
        ->assertCalled('Browser.Open', fn (array $params): bool => $params['url'] === 'https://discord.gg/nativephp')
        ->assertCalled('Browser.Open', fn (array $params): bool => $params['url'] === 'https://github.com/NativePHP')
        ->assertCalledTimes('Browser.OpenInApp', 1)
        ->assertCalledTimes('Browser.Open', 2)
        ->assertCallOrder([
            'Browser.OpenInApp',
            'Browser.Open',
            'Browser.Open',
        ]);
});
