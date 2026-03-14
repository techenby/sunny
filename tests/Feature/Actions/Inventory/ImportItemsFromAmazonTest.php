<?php

use App\Actions\Inventory\ImportItemsFromAmazonAction;
use App\Models\Team;
use Illuminate\Http\UploadedFile;

function amazonFixture(): UploadedFile
{
    return new UploadedFile(
        path: base_path('tests/Fixtures/csv/amazon-import.csv'),
        originalName: 'amazon-import.csv',
        test: true,
    );
}

test('it imports items from amazon csv', function () {
    $team = Team::factory()->create();

    (new ImportItemsFromAmazonAction)->handle(amazonFixture(), $team);

    // 6 data rows in fixture, 1 gift row skipped
    expect($team->items)->toHaveCount(5);
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
