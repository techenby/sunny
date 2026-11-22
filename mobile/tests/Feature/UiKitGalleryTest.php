<?php

use Native\Mobile\Edge\NativeRouter;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => '']);
    seedSunnyData();
});

afterEach(fn () => NativeRouter::clearRoutes());

function loadMobileRoutesFor(string $environment): void
{
    app()->detectEnvironment(fn (): string => $environment);

    NativeRouter::clearRoutes();

    require base_path('routes/mobile.php');
}

it('leaves the UI kit gallery and its Preferences row out of a release build', function () {
    loadMobileRoutesFor('production');

    expect(NativeRouter::resolve('/ui-kit'))->toBeNull()
        ->and(NativeRouter::isNativeRoute('/ui-kit'))->toBeFalse()
        ->and(NativeRouter::resolve('/preferences'))->not->toBeNull();

    Native::visit('/preferences')
        ->assertDontSee('Developer')
        ->assertDontSee('UI kit')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'ui-kit')
        ->assertMissingElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'developer');
});

it('opens the UI kit gallery from Preferences on a development build', function () {
    loadMobileRoutesFor('local');

    expect(NativeRouter::isNativeRoute('/ui-kit'))->toBeTrue();

    Native::visit('/preferences')
        ->assertSee('Developer')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'ui-kit'
            && $node['props']['a11y_label'] === 'UI kit')
        ->tap('ui-kit')
        ->assertNavigatedTo('/ui-kit');
});

it('shows every kit piece under the name a developer types', function (string $platform) {
    loadMobileRoutesFor('local');

    $gallery = Native::visit('/ui-kit', platform: $platform)
        ->assertNavTitle('UI kit')
        ->assertSee('x-ui.empty-state')
        ->assertSee('x-ui.avatar')
        ->assertSee('x-ui.badge')
        ->assertSee('x-ui.alert')
        ->assertSee('App\Ui\Card::classes()')
        ->assertSee('native.owned-row')
        ->assertSee('native.inventory-item-row')
        ->assertSee('native.settings-row')
        ->assertAccessible();

    foreach (['primary', 'secondary', 'glass', 'text', 'destructive'] as $look) {
        $gallery->assertSee('variant="'.$look.'"')
            ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'ui-kit-button-'.$look)
            ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'ui-kit-button-'.$look.'-disabled'
                && str_ends_with($node['props']['a11y_label'], ', unavailable'));
    }

    $gallery->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'ui-kit-button-menu-icon'
        && collect($node['children'])->where('type', 'top_bar_action')->map(fn (array $item): array => [$item['ref'], $item['props']['destructive'] ?? false])->values()->all() === [['ui-kit-menu-edit', false], ['ui-kit-menu-share', false], ['ui-kit-menu-delete', true]]);

    foreach (['secondary', 'glass', 'text'] as $look) {
        $gallery->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'ui-kit-button-'.$look.'-icon-only' && $node['layout']['width'] == 48);
    }
})->with(['ios', 'android']);

it('answers a tap on a gallery example', function () {
    loadMobileRoutesFor('local');

    Native::visit('/ui-kit')
        ->assertSee('Tap an example to check it responds.')
        ->tap('ui-kit-button-secondary')
        ->assertSee('Last tapped: secondary')
        ->tap('ui-kit-button-primary')
        ->assertSee('Last tapped: primary')
        ->toggle('ui-kit-settings-row-toggle-toggle', false)
        ->assertSet('toggled', false)
        ->tap('ui-kit-button-navigate')
        ->assertNavigatedTo('/inventory/0');
});
