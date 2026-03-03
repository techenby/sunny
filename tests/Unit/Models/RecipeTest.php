<?php

use App\Models\Recipe;
use App\Models\Team;

test('slug is auto-generated from name', function () {
    $recipe = Recipe::create([
        'team_id' => Team::factory()->create()->id,
        'name' => 'Chocolate Cake',
    ]);

    expect($recipe)->slug->toBe('chocolate-cake');
});
