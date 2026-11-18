<?php

use App\Support\CrashReports;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => '']);
    Storage::fake();
    seedSunnyData();
});

function withCrashReports(bool $sharing = false, ?bool $share = null, bool $saves = true): MockInterface
{
    config(['nativewatch.key' => 'nw_test']);

    return test()->partialMock(CrashReports::class, function (MockInterface $mock) use ($sharing, $share, $saves): void {
        $mock->shouldReceive('sharing')->andReturn($sharing, ...($share !== null && $saves ? [$share] : []));

        if ($share === null) {
            $mock->shouldNotReceive('share');
        } else {
            $mock->shouldReceive('share')->once()->with($share)->andReturn($saves);
        }
    });
}

it('hides the crash reports setting when NativeWatch is not set up', function () {
    Native::visit('/preferences')
        ->assertSet('canShareCrashReports', false)
        ->assertMissingElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'crash-reports');
});

it('shows crash reports off until the user turns them on', function () {
    withCrashReports();

    Native::visit('/preferences', platform: 'ios')
        ->assertSee('Share crash reports')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'crash-reports'
            && ($node['props']['trailing_type'] ?? null) === 'switch'
            && ($node['props']['trailing_checked'] ?? null) === false)
        ->assertAccessible();
});

it('saves the choice to share crash reports', function (bool $share) {
    withCrashReports(sharing: ! $share, share: $share);

    Native::visit('/preferences')
        ->toggle('setCrashReports', $share)
        ->assertSet('shareCrashReports', $share)
        ->assertNativeNotCalled('ToastKit.Show');

    expect(Storage::exists('crash-reports-asked'))->toBeTrue();
})->with([true, false]);

it('keeps crash reports off and says so when the choice cannot be saved', function () {
    withCrashReports(share: true, saves: false);

    Native::visit('/preferences')
        ->toggle('setCrashReports', true)
        ->assertSet('shareCrashReports', false)
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Unable to save your choice. Please try again.' && $params['variant'] === 'error');
});

it('asks on the dashboard whether to share crash reports', function () {
    withCrashReports();

    Native::visit('/dashboard')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'crash-reports' && $params['title'] === 'Help improve Sunny?');

    expect(Storage::exists('crash-reports-asked'))->toBeTrue();
});

it('does not ask about crash reports again', function () {
    withCrashReports();
    Storage::put('crash-reports-asked', now()->toIso8601String());

    Native::visit('/dashboard')->assertNativeNotCalled('Dialog.Alert');
});

it('does not ask when NativeWatch is not set up or reports are already shared', function (bool $configured) {
    if ($configured) {
        withCrashReports(sharing: true);
    }

    Native::visit('/dashboard')->assertNativeNotCalled('Dialog.Alert');
})->with(['not set up' => false, 'already shared' => true]);

it('shares crash reports only when the user taps Share', function () {
    withCrashReports(share: true);

    Native::visit('/dashboard')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Share', 'id' => 'crash-reports'])
        ->assertNativeNotCalled('ToastKit.Show');
});

it('leaves crash reports off when the user taps Not now', function () {
    withCrashReports();

    Native::visit('/dashboard')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Not now', 'id' => 'crash-reports']);
});

it('says so when sharing from the prompt cannot be saved', function () {
    withCrashReports(share: true, saves: false);

    Native::visit('/dashboard')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Share', 'id' => 'crash-reports'])
        ->assertToastShown(fn (array $params): bool => str_starts_with($params['message'], 'Unable to save your choice.') && $params['variant'] === 'error');
});
