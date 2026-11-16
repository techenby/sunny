<?php

use App\Models\Recipe;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('cook mode tracks ingredients, steps and timers', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Apple Cider Donuts',
        'servings' => '12',
        'ingredients' => "# Wet Ingredients\n1/4 cup granulated sugar\n2 Tbsp brown sugar, packed\n# Dry Ingredients\n1 1/2 cups all purpose flour\n1/2 tsp salt",
        'instructions' => "Preheat oven to 375°F and spray donut pans with cooking spray.\nWhisk for 1 second, then rest for 3 minutes.\nBake for 8–9 minutes. After removing from oven, allow donuts to cool in pan for 3–5 minutes.",
    ]);

    actingAs($user);

    $page = visit(route('recipes.cook', $recipe, absolute: false));

    $page->assertNoJavaScriptErrors()
        ->assertSee('0 of 4 ready')
        ->assertSee('Step 1 of 3')
        ->click('1/4 cup granulated sugar')
        ->assertSee('1 of 4 ready')
        ->click('[aria-label="More servings"]')
        ->assertSee('13')
        ->click('[wire\\:key="step-2"] > span')
        ->assertSee('Step 2 of 3')
        ->assertSee('then 3 min')
        ->click('[wire\\:key="step-2"] button')
        ->assertSee('Step 2 timer is done')
        ->assertSee('Start timer 3:00')
        ->assertSee('Step 2 of 3')
        ->assertNoJavaScriptErrors();
});
