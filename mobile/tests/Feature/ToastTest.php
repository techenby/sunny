<?php

use App\Support\Toast;
use App\Support\ToastAction;
use Illuminate\Support\Facades\View;
use Native\Mobile\System;
use Native\Mobile\Testing\Native;
use Tests\Fixtures\ToastScreen;
use Victorycodedev\ToastKit\Events\ToastActionPressed;
use Victorycodedev\ToastKit\ToastKit;

beforeEach(function () {
    View::addLocation(base_path('tests/Fixtures/views'));
});

afterEach(function () {
    System::rememberAppearance('light');
    System::rememberPlatform(null);
});

function shownToast(): array
{
    $calls = array_values(array_filter(Native::fakeBridge()->calls, fn (array $call): bool => $call['method'] === 'ToastKit.Show'));

    expect($calls)->toHaveCount(1);

    return $calls[0]['params'];
}

it('styles each kind of toast with the app theme', function (string $kind, string $ios, string $android, string $iconColor) {
    Native::fakeBridge();

    Toast::{$kind}('Moved to Basement');

    expect(shownToast())
        ->variant->toBe($kind)
        ->message->toBe('Moved to Basement')
        ->title->toBeNull()
        ->icon->toMatchArray(['ios' => $ios, 'android' => $android])
        ->style->toMatchArray([
            'background' => '#FEF9F7',
            'foreground' => '#0B0808',
            'icon_color' => $iconColor,
            'action_color' => '#C62C19',
            'corner_radius' => 18,
        ])
        ->title_text->toBe(['font' => 'semibold'])
        ->text->toBe(['font' => 'medium'])
        ->animation->toBe('slide')
        ->unique_key->toBe('Moved to Basement');
})->with([
    'success' => ['success', 'checkmark.circle.fill', 'check_circle', '#16A34A'],
    'info' => ['info', 'info.circle.fill', 'info', '#5D5350'],
    'error' => ['error', 'exclamationmark.circle.fill', 'error', '#B91C1C'],
]);

it('uses the dark theme colours in dark mode', function () {
    Native::fakeBridge();
    System::rememberAppearance('dark');

    Toast::success('Moved to Basement');

    expect(shownToast()['style'])->toMatchArray([
        'background' => '#2C2422',
        'foreground' => '#FEF9F7',
        'icon_color' => '#22C55E',
        'action_color' => '#F87966',
    ]);
});

it('shows a title above the message', function () {
    Native::fakeBridge();

    Toast::info('Your previously downloaded data is still available.', title: 'Unable to sync');

    expect(shownToast())
        ->title->toBe('Unable to sync')
        ->message->toBe('Your previously downloaded data is still available.');
});

it('keeps errors and toasts with an action up longer', function (Closure $show, int $duration) {
    Native::fakeBridge();

    $show();

    expect(shownToast())
        ->duration->toBe($duration)
        ->persistent->toBeFalse();
})->with([
    'success' => [fn () => Toast::success('Saved'), Toast::SHORT],
    'info' => [fn () => Toast::info('Saved'), Toast::SHORT],
    'error' => [fn () => Toast::error('Unable to save'), Toast::LONG],
    'long info' => [fn () => Toast::info('Saved', long: true), Toast::LONG],
    'short error' => [fn () => Toast::error('Unable to save', long: false), Toast::SHORT],
    'with an action' => [fn () => Toast::success('Saved', action: new ToastAction('View', 'view')), Toast::LONG],
]);

it('shows toasts at the top on iOS and the bottom on Android', function (string $platform, string $position) {
    Native::fakeBridge();
    System::rememberPlatform($platform);

    Toast::success('Saved');

    expect(shownToast()['position'])->toBe($position);
})->with([
    ['ios', 'top'],
    ['android', 'bottom'],
]);

it('sends one action with its data', function () {
    Native::fakeBridge();

    Toast::success('6 copies made', action: new ToastAction('View', 'view-copies', ['item' => 7]));

    expect(shownToast())
        ->action->toBe(['id' => 'view-copies', 'label' => 'View'])
        ->data->toBe(['item' => 7])
        ->not->toHaveKey('actions');
});

it('leaves the data out when the action has none', function () {
    Native::fakeBridge();

    Toast::success('Saved', action: new ToastAction('Undo', 'undo'));

    expect(shownToast())->not->toHaveKey('data');
});

it('hands a pressed action to the screen listening for its id', function () {
    Native::test(ToastScreen::class)
        ->emitNative(ToastActionPressed::class, ['toastId' => 'a', 'actionId' => 'something-else', 'data' => ['item' => 3]])
        ->assertSet('opened', null)
        ->emitNative(ToastActionPressed::class, ['toastId' => 'b', 'actionId' => 'open-item', 'data' => ['item' => 7]])
        ->assertSet('opened', 7)
        ->assertSee('Opened 7');
});

it('falls back to a plain toast when the device has no ToastKit', function () {
    Native::fakeBridge()->withoutCapability('ToastKit.Show');

    Toast::info('Your previously downloaded data is still available.', title: 'Unable to sync');

    Native::fakeBridge()
        ->assertNotCalled('ToastKit.Show')
        ->assertCalled('Dialog.Toast', fn (array $params): bool => $params === [
            'message' => "Unable to sync\nYour previously downloaded data is still available.",
            'duration' => 'short',
        ]);
});

it('falls back to a plain toast when ToastKit is not registered', function () {
    Native::fakeBridge();
    app()->offsetUnset(ToastKit::class);

    Toast::error('Unable to save');

    Native::fakeBridge()
        ->assertNotCalled('ToastKit.Show')
        ->assertCalled('Dialog.Toast', fn (array $params): bool => $params === ['message' => 'Unable to save', 'duration' => 'long']);
});

it('falls back to a plain toast when ToastKit turns the toast down', function () {
    Native::fakeBridge()->respondTo('ToastKit.Show', ['status' => 'error', 'message' => 'Invalid toast']);

    Toast::error('Unable to save');

    Native::fakeBridge()->assertCalled('Dialog.Toast', fn (array $params): bool => $params['message'] === 'Unable to save');
});
