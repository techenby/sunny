<?php

use App\Models\Team;
use App\Models\User;

use function Pest\Laravel\actingAs;

$stubReload = <<<'JS'
    window.reloaded = false
    window.confirmed = false
    window.realFetch = window.fetch
    window.confirm = () => window.confirmed = true
    window.kioskRecovery.reload = () => window.reloaded = true
    window.kioskRecovery.retryAfter = 100
JS;

$failLivewireWith = fn (string $failure): string => <<<JS
    window.fetch = (url, options) => String(url).includes('/livewire')
        ? {$failure}
        : window.realFetch(url, options)
JS;

$refresh = 'Livewire.all()[0].$wire.$refresh().catch(() => {})';

test('the kiosk reloads after repeated failed requests', function () use ($stubReload, $failLivewireWith, $refresh) {
    actingAs(User::factory()->create());

    $page = visit(route('kiosk.lists', absolute: false));

    $page->assertNoJavaScriptErrors()
        ->script($stubReload);

    $page->script($failLivewireWith("Promise.reject(new TypeError('Failed to fetch'))"));
    $page->script($refresh);
    $page->wait(0.3)->script($refresh);

    $page->wait(0.5)
        ->assertScript('window.kioskRecovery.failures', 2)
        ->assertScript('window.reloaded', false)
        ->script($refresh);

    $page->wait(0.5)
        ->assertScript('window.reloaded', true);
});

test('a successful request resets the failure count', function () use ($stubReload, $failLivewireWith, $refresh) {
    actingAs(User::factory()->create());

    $page = visit(route('kiosk.lists', absolute: false));

    $page->assertNoJavaScriptErrors()
        ->script($stubReload);

    $page->script($failLivewireWith("Promise.reject(new TypeError('Failed to fetch'))"));
    $page->script($refresh);

    $page->wait(0.5)
        ->assertScript('window.kioskRecovery.failures', 1)
        ->script('window.fetch = window.realFetch');

    $page->script($refresh);

    $page->wait(0.5)
        ->assertScript('window.kioskRecovery.failures', 0);
});

test('an expired session reloads without asking', function () use ($stubReload, $failLivewireWith, $refresh) {
    actingAs(User::factory()->create());

    $page = visit(route('kiosk.lists', absolute: false));

    $page->assertNoJavaScriptErrors()
        ->script($stubReload);

    $page->script($failLivewireWith("Promise.resolve(new Response('', { status: 419 }))"));
    $page->script($refresh);

    $page->wait(0.5)
        ->assertScript('window.reloaded', true)
        ->assertScript('window.confirmed', false);
});

test('recovery waits for the page to load before reloading', function () use ($stubReload) {
    actingAs(User::factory()->create());

    $page = visit(route('kiosk.lists', absolute: false));

    $page->assertNoJavaScriptErrors()
        ->script($stubReload);

    $page->script("window.fetch = () => Promise.resolve(new Response('', { status: 503 }))");
    $page->script('window.kioskRecovery.recover()');

    $page->wait(0.5)
        ->assertScript('window.reloaded', false)
        ->script('window.fetch = window.realFetch');

    $page->wait(0.5)
        ->assertScript('window.reloaded', true);
});

test('the screensaver comes back after a reload', function () {
    $team = Team::factory()->create(['screensaver_after' => 1, 'return_home_after' => 0]);
    actingAs(User::factory()->memberOf($team)->create());

    $page = visit(route('kiosk.lists', absolute: false));

    $page->assertNoJavaScriptErrors()
        ->script("Alpine.\$data(document.querySelector('[data-kiosk-screensaver]')).lastActivityAt -= 61 * 1000");

    $page->script('window.beforeReload = true; window.location.reload()');

    $page->wait(2)
        ->assertScript('typeof window.beforeReload', 'undefined')
        ->assertVisible('[data-kiosk-screensaver]');
});
