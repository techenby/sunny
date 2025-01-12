<?php

use App\Jobs\ImportLegoParts;
use App\Models\LegoGroup;
use App\Models\LegoPart;

test('can get categories', function () {
    (new ImportLegoParts)->getCategories();

    expect(LegoGroup::count())->toBe(14);

    $this->assertDatabaseHas('lego_groups', [
        'name' => 'Basic',
        'slug' => 'basic',
        'href' => 'https://brickarchitect.com/parts/category-1',
        'summary' => 'Classic LEGO Bricks, Plates, and Tiles can be stacked vertically by attaching the round studs with a small amount of pressure.',
    ]);
});

test('can get subcategories for category', function () {
    $category = LegoGroup::factory()->create(['name' => 'Basic', 'slug' => 'basic', 'href' => 'https://brickarchitect.com/parts/category-1']);

    (new ImportLegoParts)->getSubcategories($category);

    $basicSubCategories = LegoGroup::where('parent_id', $category->id)->get()->pluck('slug');
    expect($basicSubCategories)->toHaveCount(5)
        ->toContain(
            'basic-brick',
            'basic-plate',
            'basic-blate',
            'basic-tile',
            'basic-baseplate',
        );
});

test('can get sub-subcategories for category', function () {
    $parent = LegoGroup::factory()->create(['name' => 'Basic', 'slug' => 'basic', 'href' => 'https://brickarchitect.com/parts/category-1']);
    $category = LegoGroup::factory()->create(['parent_id' => $parent->id, 'name' => 'Brick', 'slug' => 'basic-brick', 'href' => 'https://brickarchitect.com/parts/category-15']);

    (new ImportLegoParts)->getSubcategories($category);

    $brickSubCategories = LegoGroup::where('parent_id', $category->id)->get()->pluck('slug');
    expect($brickSubCategories)->toHaveCount(4)
        ->toContain(
            'basic-brick-1-brick',
            'basic-brick-2-brick',
            'basic-brick-tall-brick',
            'basic-brick-hollow-brick',
        );
});

test('has_parts is true for group without subcategories', function () {
    $parent = LegoGroup::factory()->create(['name' => 'Wall', 'slug' => 'wall']);
    $category = LegoGroup::factory()->create(['parent_id' => $parent->id, 'name' => 'Fence', 'slug' => 'wall-fence', 'href' => 'https://brickarchitect.com/parts/category-20']);

    (new ImportLegoParts)->getSubcategories($category);

    expect(LegoGroup::forParent($category)->exists())->toBeFalse();
    expect($category->fresh()->has_parts)->toBeTrue();
});

test('description is null for group without one', function () {
    $parent = LegoGroup::factory()->create(['name' => 'Wall', 'slug' => 'wall']);
    $category = LegoGroup::factory()->create(['parent_id' => $parent->id, 'name' => 'Fence', 'slug' => 'wall-fence', 'href' => 'https://brickarchitect.com/parts/category-20']);

    (new ImportLegoParts)->getSubcategories($category);

    expect($category->fresh()->description)->toBeNull();
});

test('get parts for group', function () {
    $group = LegoGroup::factory()->create([
        'name' => '1× Brick',
        'slug' => 'basic-brick-1-brick',
        'has_parts' => true,
        'href' => 'https://brickarchitect.com/parts/category-27',
    ]);

    (new ImportLegoParts)->getPartsFor($group);

    $parts = LegoPart::forGroup($group)->pluck('name');

    expect($parts)->toHaveCount(10);
});
