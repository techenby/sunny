<?php

use Native\Mobile\Plugins\Plugin;
use Native\Mobile\Plugins\PluginRegistry;

it('asks Android only for the permissions Sunny uses', function () {
    expect(app(PluginRegistry::class)->androidPermissions())->toEqualCanonicalizing([
        'android.permission.CAMERA',
        'android.permission.POST_NOTIFICATIONS',
        'android.permission.POST_PROMOTED_NOTIFICATIONS',
        'android.permission.RECEIVE_BOOT_COMPLETED',
        'android.permission.INTERNET',
        'android.permission.ACCESS_NETWORK_STATE',
        'android.permission.VIBRATE',
    ]);
});

it('adds no Android foreground services', function () {
    $services = app(PluginRegistry::class)->all()
        ->flatMap(fn (Plugin $plugin): array => $plugin->getAndroidManifest()['services'] ?? []);

    expect($services)->toBeEmpty();
});

it('asks iOS only for the permissions Sunny uses', function () {
    expect(array_keys(app(PluginRegistry::class)->iosInfoPlist()))->toEqualCanonicalizing([
        'NSCameraUsageDescription',
        'NSPhotoLibraryUsageDescription',
        'NSSupportsLiveActivities',
    ]);
});

it('declares the data Sunny collects in the iOS privacy manifest', function () {
    $manifest = simplexml_load_file(base_path('vendor/nativephp/mobile/resources/xcode/NativePHP/PrivacyInfo.xcprivacy'));

    $linked = collect($manifest->xpath('//key[.="NSPrivacyCollectedDataTypes"]/following-sibling::array[1]/dict'))
        ->mapWithKeys(fn (SimpleXMLElement $type): array => [
            (string) $type->xpath('key[.="NSPrivacyCollectedDataType"]/following-sibling::string[1]')[0] => $type->xpath('key[.="NSPrivacyCollectedDataTypeLinked"]/following-sibling::*[1]')[0]->getName() === 'true',
        ]);

    expect($linked->all())->toEqual([
        'NSPrivacyCollectedDataTypeEmailAddress' => true,
        'NSPrivacyCollectedDataTypeName' => true,
        'NSPrivacyCollectedDataTypePhotosorVideos' => true,
        'NSPrivacyCollectedDataTypeOtherUserContent' => true,
        'NSPrivacyCollectedDataTypeUserID' => true,
        'NSPrivacyCollectedDataTypeCrashData' => false,
        'NSPrivacyCollectedDataTypePerformanceData' => false,
        'NSPrivacyCollectedDataTypeOtherDiagnosticData' => false,
    ]);
});
