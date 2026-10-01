<?php

use App\Http\Integrations\Sunny\SunnyStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\FakeBridge;
use Saloon\Config;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

Config::preventStrayRequests();

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => AsyncTask::fake())
    ->afterEach(fn () => AsyncTask::clearFake())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function fakeSecureStorage(): FakeBridge
{
    $values = [];

    return FakeBridge::enable()
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
}

function seedSunnyData(): void
{
    $data = json_decode(file_get_contents(__DIR__.'/Fixtures/sunny.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach (['recipes', 'items'] as $type) {
        $data[$type] = array_map(fn (array $record): array => $record + ['team_id' => 1], $data[$type]);
    }
    app(SunnyStore::class)->applySnapshot([
        ...$data,
        'routines' => sunnyRoutines(),
        'routine_occurrences' => sunnyRoutineOccurrences(),
        'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
        'synced_at' => now()->toIso8601String(),
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function sunnyRoutines(int $teamId = 1): array
{
    $routine = fn (int $id, string $name, string $timeOfDay, string $frequency, ?array $weekdays, ?int $dayOfMonth, array $steps, bool $active = true): array => [
        'id' => $id,
        'team_id' => $teamId,
        'name' => $name,
        'time_of_day' => $timeOfDay,
        'frequency' => $frequency,
        'weekdays' => $weekdays,
        'day_of_month' => $dayOfMonth,
        'is_active' => $active,
        'client_uuid' => null,
        'deleted_at' => null,
        'user' => null,
        'steps' => collect($steps)->map(fn (string $step, int $index): array => [
            'id' => $id * 10 + $index + 1, 'routine_id' => $id, 'name' => $step, 'position' => $index + 1,
        ])->all(),
    ];

    return [
        $routine(1, 'Get ready', 'morning', 'daily', null, null, ['Make bed', 'Feed the cat', 'Water plants']),
        $routine(2, 'Bedtime', 'evening', 'weekly', [1, 3, 5], null, ['Pajamas', 'Brush teeth']),
        $routine(3, 'Take out trash', 'anytime', 'monthly', null, 15, ['Kitchen bin']),
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function sunnyRoutineOccurrences(int $teamId = 1): array
{
    $occurrence = fn (int $id, string $dueOn, string $name, string $timeOfDay, ?string $assignee, array $steps): array => [
        'id' => $id,
        'routine_id' => $id,
        'due_on' => $dueOn,
        'routine' => ['id' => $id, 'team_id' => $teamId, 'name' => $name, 'time_of_day' => $timeOfDay, 'user' => $assignee ? ['id' => 7, 'name' => $assignee] : null],
        'steps' => collect($steps)->map(fn (array $step, int $index): array => [
            'id' => $id * 10 + $index + 1, 'name' => $step[0], 'position' => $index + 1, 'completed_at' => $step[1],
        ])->all(),
    ];

    return [
        $occurrence(2, now()->toDateString(), 'Bedtime', 'evening', 'Sam', [['Pajamas', null], ['Brush teeth', null]]),
        $occurrence(1, now()->toDateString(), 'Get ready', 'morning', null, [['Make bed', '2026-09-30T13:00:00.000000Z'], ['Feed the cat', null], ['Water plants', null]]),
        $occurrence(3, now()->addDay()->toDateString(), 'Take out trash', 'anytime', null, [['Kitchen bin', null]]),
    ];
}
