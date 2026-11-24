<?php

declare(strict_types=1);

namespace Tests;

use App\Support\HostResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->resolveHostsTo(['93.184.216.34']);
    }

    /** @param  list<string>  $addresses */
    protected function resolveHostsTo(array $addresses): void
    {
        $this->app->instance(HostResolver::class, new class($addresses) extends HostResolver
        {
            /** @param  list<string>  $addresses */
            public function __construct(private array $addresses) {}

            public function addresses(string $host): array
            {
                return $this->addresses;
            }
        });
    }
}
