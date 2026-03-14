<?php

use App\Actions\Inventory\ImportItemsFromAmazonAction;
use App\Models\Team;

test('can import items from amazon csv', function () {
    $team = Team::factory()->create();

    $result = (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team);

    expect($team->items)->toHaveCount(7)
        ->and($result)->skipped->toBe(0)->imported->toBe(7);
});

test('can decode html entities in product names', function () {
    $team = Team::factory()->create();

    (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team);

    expect($team->items()->pluck('name'))->toContain(
        '［20 Pack］608-2RS Ball Bearings - Bearing Steel and Double Rubber Sealed Miniature Deep Groove Ball Bearings (8mm x 22mm x 7mm)',
        '4-Pack Mini Digital Humidity Temperature Meters Gauge Indoor Hygrometer Thermometer with LCD Display Fahrenheit (℉)',
        'Aluminum Rollator Walker with Seat Folding with 10-inch Front Wheels for Senior(Blue）',
    );
});

test('can filter out gifts when checked', function () {
    $team = Team::factory()->create();

    $result = (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team, filters: ['filterGifts' => true]);

    expect($team->items()->pluck('name'))->not->toContain('Example Gift Item - Board Game')
        ->and($team->items)->toHaveCount(6)
        ->and($result)->skipped->toBe(1)->imported->toBe(6);
});

test('can filter out consumable items when checked', function () {
    $team = Team::factory()->create();

    $result = (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team, filters: ['filterConsumables' => true]);

    expect($team->items()->pluck('name'))->not->toContain('Diet Coke 12-Pack Cans')
        ->and($team->items)->toHaveCount(6)
        ->and($result)->skipped->toBe(1)->imported->toBe(6);
});

test('can filter items by date range', function () {
    $team = Team::factory()->create();

    $result = (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team, filters: [
        'startDate' => '2022-01-01',
        'endDate' => '2024-12-31',
    ]);

    // Items in range: Flag (2024-06-05), Medieval (2024-06-05), Walker (2022-01-29), Diet Coke (2023-03-15)
    // Out of range: Ball Bearings (2021-07-12), Hygrometer (2021-08-11)
    expect($team->items)->toHaveCount(5)
        ->and($result)->skipped->toBe(2)->imported->toBe(5);
});

test('can filter items by start date only', function () {
    $team = Team::factory()->create();

    $result = (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team, filters: [
        'startDate' => '2024-01-01',
    ]);

    // Items from 2024+: Flag (2024-06-05), Medieval (2024-06-05)
    expect($team->items)->toHaveCount(3)
        ->and($result)->skipped->toBe(4)->imported->toBe(3);
});

test('can filter items by end date only', function () {
    $team = Team::factory()->create();

    $result = (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team, filters: [
        'endDate' => '2022-12-31',
    ]);

    // Items before 2023: Ball Bearings (2021-07-12), Hygrometer (2021-08-11), Walker (2022-01-29)
    expect($team->items)->toHaveCount(3)
        ->and($result)->skipped->toBe(4)->imported->toBe(3);
});

test('can use all filters', function () {
    $team = Team::factory()->create();

    $result = (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team, filters: [
        'filterGifts' => true,
        'filterConsumables' => true,
        'startDate' => '2022-01-01',
        'endDate' => '2024-12-31',
    ]);

    // In range and not gift/consumable: Flag, Medieval, Walker
    expect($team->items)->toHaveCount(3)
        ->and($result)->skipped->toBe(4)->imported->toBe(3);
});
