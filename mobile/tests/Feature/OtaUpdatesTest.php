<?php

use App\Providers\NativeServiceProvider;
use Native\Mobile\Testing\Native;
use Nativephp\MobileOta\Ota;
use Nativephp\MobileOta\OtaServiceProvider;

it('compiles the OTA plugin into native builds', function (): void {
    expect((new NativeServiceProvider(app()))->plugins())->toContain(OtaServiceProvider::class);
});

it('defaults OTA updates to silent mode', function (): void {
    expect(config('nativephp-ota.mode'))->toBe('silent');
});

it('checks for an update on launch without downloading when the app is up to date', function (): void {
    $bridge = Native::fakeBridge()->withOtaUpToDate();

    app(Ota::class)->onLaunch();

    $bridge->assertOtaChecked()->assertNothingOtaDownloaded();
});

it('downloads and queues a waiting update on launch', function (): void {
    $bridge = Native::fakeBridge()
        ->withOtaUpdate()
        ->respondTo('Ota.Download', ['success' => true]);

    app(Ota::class)->onLaunch();

    $bridge->assertOtaChecked()->assertOtaDownloaded()->assertOtaQueued();
});
