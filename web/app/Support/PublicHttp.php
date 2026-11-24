<?php

declare(strict_types=1);

namespace App\Support;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PublicHttp
{
    public const int MAX_BYTES = 5 * 1024 * 1024;

    public const int MAX_REDIRECTS = 3;

    public const int CONNECT_TIMEOUT = 5;

    public function __construct(private HostResolver $resolver) {}

    /**
     * @param  array<string, string>  $headers
     *
     * @throws ConnectionException
     */
    public function get(string $url, array $headers = [], int $timeout = 10): Response
    {
        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $response = $this->send($url, $headers, $timeout);
            $location = $response->header('Location');

            if (! $response->redirect() || $location === '') {
                return $response;
            }

            $url = $this->resolveRedirect($url, $location);
        }

        throw new ConnectionException('The address redirected too many times.');
    }

    public static function isPublicAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
            return false;
        }

        $packed = inet_pton($address);

        if ($packed === false) {
            return false;
        }

        $firstByte = ord($packed[0]);

        return match (strlen($packed)) {
            4 => $firstByte < 224,
            default => $firstByte !== 0xFF && ! str_starts_with($packed, "\x00\x64\xff\x9b"),
        };
    }

    /** @param  array<string, string>  $headers */
    private function send(string $url, array $headers, int $timeout): Response
    {
        [$host, $port] = $this->hostAndPort($url);
        $addresses = $this->publicAddresses($host);

        $options = [
            'progress' => function (int|float $downloadTotal, int|float $downloaded): void {
                throw_if(max($downloadTotal, $downloaded) > self::MAX_BYTES, RuntimeException::class, 'The response is too large.');
            },
        ];

        if ($addresses !== [$host]) {
            $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:" . implode(',', $addresses)]];
        }

        return Http::withHeaders($headers)
            ->withoutRedirecting()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout($timeout)
            ->withOptions($options)
            ->get($url);
    }

    /** @return array{string, int} */
    private function hostAndPort(string $url): array
    {
        throw_if(preg_match('/[\\s\\\\\x00-\x1f\x7f]/', $url) === 1, ConnectionException::class, 'The address is not valid.');

        try {
            $uri = new Uri($url);
        } catch (Throwable $exception) {
            throw new ConnectionException('The address is not valid.', previous: $exception);
        }

        $scheme = strtolower($uri->getScheme());
        $host = trim($uri->getHost(), '[]');

        throw_unless(in_array($scheme, ['http', 'https'], true) && $host !== '', ConnectionException::class, 'The address is not valid.');

        return [$host, $uri->getPort() ?? ($scheme === 'https' ? 443 : 80)];
    }

    /** @return list<string> */
    private function publicAddresses(string $host): array
    {
        $addresses = filter_var($host, FILTER_VALIDATE_IP) === false
            ? $this->resolver->addresses($host)
            : [$host];

        throw_if(
            $addresses === [] || collect($addresses)->contains(fn (string $address): bool => ! self::isPublicAddress($address)),
            ConnectionException::class,
            'The address is not on the public internet.',
        );

        return $addresses;
    }

    private function resolveRedirect(string $url, string $location): string
    {
        try {
            return (string) UriResolver::resolve(new Uri($url), new Uri($location));
        } catch (Throwable $exception) {
            throw new ConnectionException('The address redirected somewhere that is not valid.', previous: $exception);
        }
    }
}
