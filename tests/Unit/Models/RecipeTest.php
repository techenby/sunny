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

test('duplicate slugs append counter within same team', function () {
    $team = Team::factory()->create();

    $recipe1 = Recipe::create([
        'team_id' => $team->id,
        'name' => 'Chocolate Cake',
    ]);
    expect($recipe1)->slug->toBe('chocolate-cake');

    $recipe2 = Recipe::create([
        'team_id' => $team->id,
        'name' => 'Chocolate Cake',
    ]);
    expect($recipe2)->slug->toBe('chocolate-cake-1');

    $recipe3 = Recipe::create([
        'team_id' => $team->id,
        'name' => 'Chocolate Cake',
    ]);
    expect($recipe3)->slug->toBe('chocolate-cake-2');
});

test('same slug can exist in different teams', function () {
    $team1 = Team::factory()->create();
    $team2 = Team::factory()->create();

    $recipe1 = Recipe::create([
        'team_id' => $team1->id,
        'name' => 'Chocolate Cake',
    ]);
    expect($recipe1)->slug->toBe('chocolate-cake');

    $recipe2 = Recipe::create([
        'team_id' => $team2->id,
        'name' => 'Chocolate Cake',
    ]);
    expect($recipe2)->slug->toBe('chocolate-cake');
});

test('updating name generates new unique slug', function () {
    $team = Team::factory()->create();

    Recipe::create([
        'team_id' => $team->id,
        'name' => 'Chocolate Cake',
    ]);

    $recipe2 = Recipe::create([
        'team_id' => $team->id,
        'name' => 'Vanilla Cake',
    ]);
    expect($recipe2)->slug->toBe('vanilla-cake');

    $recipe2->update(['name' => 'Chocolate Cake']);
    expect($recipe2->fresh())->slug->toBe('chocolate-cake-1');
});

test('updating without changing name does not change slug', function () {
    $recipe = Recipe::create([
        'team_id' => Team::factory()->create()->id,
        'name' => 'Chocolate Cake',
    ]);

    $originalSlug = $recipe->slug;
    $recipe->update(['servings' => '8']);

    expect($recipe->fresh())->slug->toBe($originalSlug);
});

test('updating keeps existing slug if still unique', function () {
    $team = Team::factory()->create();

    $recipe = Recipe::create([
        'team_id' => $team->id,
        'name' => 'Chocolate Cake',
    ]);

    $recipe->update(['name' => 'Chocolate Cake Modified']);

    expect($recipe->fresh())->slug->toBe('chocolate-cake-modified');
});

test('source can be shortened', function () {
    $recipe = Recipe::factory()->make(['source' => 'https://www.italianfoodforever.com/2018/12/buttery-toasted-almond-crescent-cookies/?fbclid=IwAR35HddGDTbORlGEwro1CehIKuGDvGtwc44zMl05BLEjF4NuNF-hKDrpJPU']);

    expect($recipe->shortenedSource())->toBe('italianfoodforever.com');
});
