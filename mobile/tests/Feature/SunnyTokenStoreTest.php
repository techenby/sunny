<?php

use App\Http\Integrations\Sunny\SunnyTokenStore;
use Native\Mobile\Testing\FakeBridge;

beforeEach(function (): void {
    config(['services.sunny.api_url' => 'https://sunny.example/api']);
});

afterEach(fn () => FakeBridge::disable());

it('stores, reads and deletes tokens using an API-specific secure key', function (): void {
    $values = [];
    $bridge = FakeBridge::enable()
        ->respondTo('SecureStorage.Set', function (array $params) use (&$values): array {
            $values[$params['key']] = $params['value'];

            return ['success' => true];
        })
        ->respondTo('SecureStorage.Get', function (array $params) use (&$values): array {
            return ['value' => $values[$params['key']] ?? ''];
        })
        ->respondTo('SecureStorage.Delete', function (array $params) use (&$values): array {
            unset($values[$params['key']]);

            return ['success' => true];
        });

    $store = app(SunnyTokenStore::class);
    expect($store->get())->toBeNull();
    $store->put('secret-token');
    expect(app(SunnyTokenStore::class)->get())->toBe('secret-token');

    config(['services.sunny.api_url' => 'https://other.example/api']);
    expect(app(SunnyTokenStore::class)->get())->toBeNull();

    config(['services.sunny.api_url' => 'https://sunny.example/api/']);
    expect(app(SunnyTokenStore::class)->get())->toBe('secret-token');
    $store->forget();
    expect($store->get())->toBeNull();

    $bridge->assertCalled('SecureStorage.Set', fn (array $params): bool => $params === [
        'key' => 'sunny.auth.token.'.hash('sha256', 'https://sunny.example/api'),
        'value' => 'secret-token',
    ]);
});

it('does not treat locked or failed reads as a missing token', function (?array $response): void {
    $bridge = FakeBridge::enable()->respondTo('SecureStorage.Get', $response);

    expect(fn () => app(SunnyTokenStore::class)->get())->toThrow(RuntimeException::class);
    $bridge->assertNotCalled('SecureStorage.Delete')->assertNotCalled('SecureStorage.Set');
})->with([
    'locked' => [['status' => 'unavailable']],
    'failure' => [['status' => 'error', 'message' => 'Native error']],
    'bridge unavailable' => [null],
]);

it('reports unsuccessful secure writes and deletes', function (string $method): void {
    FakeBridge::enable()->respondTo('SecureStorage.Set', ['success' => false])
        ->respondTo('SecureStorage.Delete', ['success' => false]);

    expect(fn () => $method === 'put' ? app(SunnyTokenStore::class)->put('token') : app(SunnyTokenStore::class)->forget())
        ->toThrow(RuntimeException::class);
})->with(['put', 'forget']);

it('rejects empty tokens without writing to the bridge', function (): void {
    $bridge = FakeBridge::enable();

    expect(fn () => app(SunnyTokenStore::class)->put(' '))->toThrow(InvalidArgumentException::class);
    $bridge->assertNothingCalled();
});
