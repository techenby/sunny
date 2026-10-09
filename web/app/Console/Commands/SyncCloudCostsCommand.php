<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Integrations\LaravelCloud\LaravelCloudConnector;
use App\Http\Integrations\LaravelCloud\Requests\GetUsage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\File;

#[Description('Save what Sunny Home cost on Laravel Cloud in the last full billing period for the costs page')]
#[Signature('costs:sync {--period=1 : Billing period to read, where 0 is the current period and 1 is the previous one}')]
class SyncCloudCostsCommand extends Command
{
    public function handle(): int
    {
        $period = (int) $this->option('period');

        $usage = (new LaravelCloudConnector)->send(new GetUsage($period))->json();

        $applications = config('costs.cloud.applications');
        $resources = config('costs.cloud.resources');

        $sumResources = fn (string $type): int => collect(Arr::get($usage, "data.resources.{$type}", []))
            ->whereIn('name', $resources)
            ->sum(fn (array $resource): int => (int) ($resource['total_cents'] ?? 0));

        $costs = [
            'period' => Arr::get($usage, "meta.available_periods.{$period}"),
            'synced_at' => Date::now()->toIso8601String(),
            'items' => [
                'App servers' => (int) collect(Arr::get($usage, 'data.application_totals.applications', []))
                    ->whereIn('identifier', $applications)
                    ->sum('total_cost_cents'),
                'Database' => $sumResources('databases'),
                'File storage' => $sumResources('buckets'),
                'Cache' => $sumResources('caches'),
                'WebSockets' => $sumResources('websockets'),
            ],
        ];

        File::ensureDirectoryExists(dirname((string) config('costs.cloud.path')));
        File::put(config('costs.cloud.path'), json_encode($costs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        $this->components->info(__('Saved Laravel Cloud costs totaling $:total.', [
            'total' => number_format(array_sum($costs['items']) / 100, 2),
        ]));

        return self::SUCCESS;
    }
}
