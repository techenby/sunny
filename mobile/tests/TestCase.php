<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\ParallelTesting;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if ($token = ParallelTesting::token()) {
            $this->app->useStoragePath(storage_path("framework/testing/parallel-{$token}"));
        }
    }
}
