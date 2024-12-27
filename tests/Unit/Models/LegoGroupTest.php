<?php

use App\Models\LegoGroup;

test('get parents', function () {
    $basic = LegoGroup::factory()->create(['parent_id' => null, 'slug' => 'basic']);
    $brick = LegoGroup::factory()->create(['parent_id' => $basic->id, 'slug' => 'brick']);

    expect(LegoGroup::parents()->get()->pluck('id'))
        ->toHaveCount(1)
        ->toContain($basic->id)
        ->not->toContain($brick->id);
});
