<?php

use App\Support\PublicHttp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('fetches a page on the public internet', function () {
    Http::fake(['example.com/*' => Http::response('hello')]);

    expect(resolve(PublicHttp::class)->get('https://example.com/page')->body())->toBe('hello');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://example.com/page');
});

test('refuses a name that points inside the network', function (array $addresses) {
    Http::fake();
    $this->resolveHostsTo($addresses);

    expect(fn () => resolve(PublicHttp::class)->get('https://internal.example.com/'))->toThrow(ConnectionException::class);

    Http::assertNothingSent();
})->with([
    'loopback' => [['127.0.0.1']],
    'private' => [['10.0.0.5']],
    'cloud metadata' => [['169.254.169.254']],
    'carrier NAT' => [['100.64.0.1']],
    'IPv6 loopback' => [['::1']],
    'IPv6 private' => [['fd00::1']],
    'IPv4 inside IPv6' => [['::ffff:127.0.0.1']],
    'NAT64' => [['64:ff9b::7f00:1']],
    'multicast' => [['224.0.0.1']],
    'one public and one private' => [['93.184.216.34', '192.168.1.10']],
    'nothing' => [[]],
]);

test('refuses an address written as a private number', function (string $url) {
    Http::fake();

    expect(fn () => resolve(PublicHttp::class)->get($url))->toThrow(ConnectionException::class);

    Http::assertNothingSent();
})->with([
    'http://127.0.0.1/',
    'http://169.254.169.254/latest/meta-data/',
    'http://[::1]/',
    'http://192.168.0.1:8080/',
    'ftp://example.com/file',
    'http://example.com\\@127.0.0.1/',
]);

test('checks every address a redirect leads to', function () {
    Http::fake([
        'example.com/start' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
    ]);

    expect(fn () => resolve(PublicHttp::class)->get('https://example.com/start'))->toThrow(ConnectionException::class);

    Http::assertSentCount(1);
});

test('follows a redirect to another public page', function () {
    Http::fake([
        'example.com/start' => Http::response('', 301, ['Location' => '/finish']),
        'example.com/finish' => Http::response('done'),
    ]);

    expect(resolve(PublicHttp::class)->get('https://example.com/start')->body())->toBe('done');
});

test('gives up when a page keeps redirecting', function () {
    Http::fake(['example.com/*' => Http::response('', 302, ['Location' => '/again'])]);

    expect(fn () => resolve(PublicHttp::class)->get('https://example.com/start'))->toThrow(ConnectionException::class);

    Http::assertSentCount(PublicHttp::MAX_REDIRECTS + 1);
});
