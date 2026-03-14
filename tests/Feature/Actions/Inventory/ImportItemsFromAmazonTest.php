<?php

use App\Actions\Inventory\ImportItemsFromAmazonAction;
use App\Models\Team;

test('it imports items from amazon csv', function () {
    $team = Team::factory()->create();

    (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team);

    // 7 data rows in fixture, 1 gift row skipped (consumable filter off by default)
    expect($team->items)->toHaveCount(6);
    expect($team->items()->first())
        ->name->toBe('Rainbow Pride Flag 3x5ft - Vivid Color and UV Fade Resistant - Canvas Header and Brass Grommets')
        ->metadata->toMatchArray([
            'Amount Paid' => '9.71',
            'ASIN' => 'B07TLBPNWB',
            'Website' => 'Amazon.com',
        ]);
});

test('it decodes html entities in product names', function () {
    $team = Team::factory()->create();

    (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team);

    $names = $team->items()->pluck('name');

    expect($names)->toContain('［20 Pack］608-2RS Ball Bearings - Bearing Steel and Double Rubber Sealed Miniature Deep Groove Ball Bearings (8mm x 22mm x 7mm)');
    expect($names)->toContain('4-Pack Mini Digital Humidity Temperature Meters Gauge Indoor Hygrometer Thermometer with LCD Display Fahrenheit (℉)');
    expect($names)->toContain('Aluminum Rollator Walker with Seat Folding with 10-inch Front Wheels for Senior(Blue）');
});

test('it skips gift items', function () {
    $team = Team::factory()->create();

    (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team);

    $names = $team->items()->pluck('name');

    expect($names)->not->toContain('Example Gift Item - Board Game');
});

test('it can include gift items when filter is disabled', function () {
    $team = Team::factory()->create();

    (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team, filters: ['filterGifts' => false]);

    $names = $team->items()->pluck('name');

    expect($names)->toContain('Example Gift Item - Board Game');
    expect($team->items)->toHaveCount(7);
});

test('it filters out consumable items', function () {
    $team = Team::factory()->create();

    (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team, filters: ['filterConsumables' => true]);

    $names = $team->items()->pluck('name');

    expect($names)->not->toContain('Diet Coke 12-Pack Cans');
    // 7 rows - 1 gift - 1 consumable = 5
    expect($team->items)->toHaveCount(5);
});

test('it filters items by date range', function () {
    $team = Team::factory()->create();

    (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team, filters: [
        'startDate' => '2022-01-01',
        'endDate' => '2024-12-31',
    ]);

    // Items in range: Flag (2024-06-05), Medieval (2024-06-05), Walker (2022-01-29), Diet Coke (2023-03-15)
    // Out of range: Ball Bearings (2021-07-12), Hygrometer (2021-08-11)
    // Skipped: Gift (2024-12-25 but is a gift)
    expect($team->items)->toHaveCount(4);
});

test('it filters items by start date only', function () {
    $team = Team::factory()->create();

    (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team, filters: [
        'startDate' => '2024-01-01',
    ]);

    // Items from 2024+: Flag (2024-06-05), Medieval (2024-06-05)
    // Skipped: Gift (2024-12-25 is a gift)
    expect($team->items)->toHaveCount(2);
});

test('it filters items by end date only', function () {
    $team = Team::factory()->create();

    (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team, filters: [
        'endDate' => '2022-12-31',
    ]);

    // Items before 2023: Ball Bearings (2021-07-12), Hygrometer (2021-08-11), Walker (2022-01-29)
    expect($team->items)->toHaveCount(3);
});

test('it combines all filters', function () {
    $team = Team::factory()->create();

    $result = (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team, filters: [
        'filterGifts' => true,
        'filterConsumables' => true,
        'startDate' => '2022-01-01',
        'endDate' => '2024-12-31',
    ]);

    // In range and not gift/consumable: Flag, Medieval, Walker
    expect($team->items)->toHaveCount(3);
    expect($result['imported'])->toBe(3);
    expect($result['skipped'])->toBe(4);
});
