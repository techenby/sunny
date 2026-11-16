<?php

use App\Providers\NativeServiceProvider;
use Native\Mobile\Testing\Native;
use Nativephp\MobileOta\Ota;
use Nativephp\MobileOta\OtaServiceProvider;

it('compiles the OTA plugin into native builds', function (): void {
    expect((new NativeServiceProvider(app()))->plugins())->toContain(OtaServiceProvider::class);
});

it('leaves OTA updates in manual mode', function (): void {
    expect(config('nativephp-ota.mode'))->toBe('manual');
});

it('does not check for updates on launch in manual mode', function (): void {
    $bridge = Native::fakeBridge();

    app(Ota::class)->onLaunch();

    $bridge->assertNotCalled('Ota.Check');
});

it('checks for updates on launch in silent mode', function (): void {
    config(['nativephp-ota.mode' => 'silent']);
    $bridge = Native::fakeBridge()->withOtaUpToDate();

    app(Ota::class)->onLaunch();

    $bridge->assertOtaChecked()->assertNothingOtaDownloaded();
});
