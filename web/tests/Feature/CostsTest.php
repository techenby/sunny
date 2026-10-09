<?php

use App\Http\Integrations\LaravelCloud\Requests\GetUsage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Saloon;

beforeEach(function () {
    $this->path = storage_path('framework/testing/cloud-costs.json');

    config([
        'costs.cloud.path' => $this->path,
        'costs.cloud.applications' => ['app-sunny'],
        'costs.cloud.resources' => ['sunny'],
        'costs.services' => ['Domain' => 125, 'Monitoring' => null],
    ]);
});

afterEach(function () {
    File::delete($this->path);
});

test('sync saves only Sunny Home costs from the previous billing period', function () {
    Date::setTestNow('2026-10-25 12:00:00');

    Saloon::fake([
        GetUsage::class => MockResponse::make([
            'meta' => [
                'available_periods' => [
                    ['from' => 'Sep 24', 'to' => 'Oct 23'],
                    ['from' => 'Aug 24', 'to' => 'Sep 23'],
                ],
            ],
            'data' => [
                'application_totals' => [
                    'applications' => [
                        ['identifier' => 'app-sunny', 'total_cost_cents' => 756],
                        ['identifier' => 'app-other', 'total_cost_cents' => 72],
                    ],
                ],
                'resources' => [
                    'databases' => [['name' => 'sunny', 'total_cents' => 899]],
                    'buckets' => [
                        ['name' => 'sunny', 'total_cents' => 12],
                        ['name' => 'other', 'total_cents' => 40],
                    ],
                ],
            ],
        ]),
    ]);

    $this->artisan('costs:sync')->assertSuccessful();

    Saloon::assertSent(fn (GetUsage $request): bool => $request->period === 1);

    expect(File::json($this->path))->toBe([
        'period' => ['from' => 'Aug 24', 'to' => 'Sep 23'],
        'synced_at' => '2026-10-25T12:00:00+00:00',
        'items' => [
            'App servers' => 756,
            'Database' => 899,
            'File storage' => 12,
            'Cache' => 0,
            'WebSockets' => 0,
        ],
    ]);
});

test('sync fails when Laravel Cloud returns an error', function () {
    Saloon::fake([
        GetUsage::class => MockResponse::make(['message' => 'Unauthenticated.'], 401),
    ]);

    expect(fn () => $this->artisan('costs:sync'))->toThrow(Exception::class);

    expect(File::exists($this->path))->toBeFalse();
});

test('costs page shows cloud costs, filled in services, and the total', function () {
    File::put($this->path, json_encode([
        'period' => ['from' => 'Aug 24', 'to' => 'Sep 23'],
        'synced_at' => '2026-10-25T12:00:00+00:00',
        'items' => ['App servers' => 756, 'Database' => 899, 'Cache' => 0],
    ]));

    $this->get(route('costs'))
        ->assertOk()
        ->assertSee('What it costs to run Sunny Home')
        ->assertSee('Laravel Cloud: App servers')
        ->assertSee('$7.56')
        ->assertSee('$8.99')
        ->assertDontSee('Laravel Cloud: Cache')
        ->assertSee('Domain')
        ->assertDontSee('Monitoring')
        ->assertSee('$17.80')
        ->assertSee('Aug 24 to Sep 23')
        ->assertSee(config('costs.sponsor_url'));
});

test('costs page works before the first sync', function () {
    $this->get(route('costs'))
        ->assertOk()
        ->assertSee('$1.25')
        ->assertDontSee('billing period');
});
