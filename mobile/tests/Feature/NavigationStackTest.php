<?php

use App\NativeComponents\Dashboard;
use App\NativeComponents\Home;
use App\NativeComponents\Preferences;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Edge\NativeRouter;
use Native\Mobile\Edge\NavigationIntent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;
use PHPUnit\Framework\AssertionFailedError;

class StackRouter extends NativeRouter
{
    public function push(NativeComponent $component, string $uri): void
    {
        $this->stack[] = ['component' => $component, 'uri' => $uri, 'params' => []];
    }

    public function replaceTop(NavigationIntent $intent): void
    {
        $this->popForReplace($this->stack[count($this->stack) - 1]['component'], $intent);
    }
}

beforeEach(function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => '']);
    seedSunnyData();
});

it('leaves nothing to go back to after logging out from Settings opened on Summary', function () {
    $summary = Native::visit('/dashboard')->assertScreen(Dashboard::class);
    $settings = $summary->tap('preferences')->follow()->assertScreen(Preferences::class);
    $settings->tap('log-out')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Log out', 'id' => 'log-out']);

    $router = new StackRouter;
    $router->push($summary->instance(), '/dashboard');
    $router->push($settings->instance(), '/preferences');
    $router->replaceTop($settings->instance()->getNavigationIntent());

    expect($router->stackDepth())->toBe(0);

    $welcome = $settings->follow()->assertScreen(Home::class);

    expect(fn () => $welcome->goBack())->toThrow(AssertionFailedError::class, 'No previous screen');
});

it('keeps the screens below a plain replace', function () {
    $summary = Native::visit('/dashboard');
    $settings = $summary->tap('preferences')->follow();

    $router = new StackRouter;
    $router->push($summary->instance(), '/dashboard');
    $router->push($settings->instance(), '/preferences');
    $router->replaceTop(new NavigationIntent(NavigationIntent::REPLACE, '/preferences/team'));

    expect($router->stackDepth())->toBe(1)
        ->and($router->currentUri())->toBe('/dashboard');
});
