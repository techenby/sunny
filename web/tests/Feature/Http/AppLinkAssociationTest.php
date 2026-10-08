<?php

test('serves the apple app site association for item links', function () {
    config(['services.sunny_app.apple_team_id' => 'ABCDE12345']);

    $this->get('/.well-known/apple-app-site-association')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertExactJson([
            'applinks' => [
                'details' => [[
                    'appIDs' => ['ABCDE12345.com.techenby.sunnyhomeapp'],
                    'components' => [['/' => '/i/*']],
                ]],
            ],
        ]);
});

test('serves the android asset links', function () {
    config(['services.sunny_app.android_sha256_cert_fingerprints' => ['AA:BB:CC']]);

    $this->get('/.well-known/assetlinks.json')
        ->assertOk()
        ->assertExactJson([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => 'com.techenby.sunnyhomeapp',
                'sha256_cert_fingerprints' => ['AA:BB:CC'],
            ],
        ]]);
});

test('association files are missing until the app is configured', function () {
    config([
        'services.sunny_app.apple_team_id' => null,
        'services.sunny_app.android_sha256_cert_fingerprints' => [],
    ]);

    $this->get('/.well-known/apple-app-site-association')->assertNotFound();
    $this->get('/.well-known/assetlinks.json')->assertNotFound();
});
