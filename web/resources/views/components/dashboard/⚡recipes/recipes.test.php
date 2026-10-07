<?php

use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

test('it shows the team\'s most recently added recipes', function () {
    $user = User::factory()->create();

    Recipe::factory()->for($user->currentTeam)->create(['name' => 'Oldest', 'created_at' => now()->subDays(10)]);
    foreach (range(1, 5) as $day) {
        Recipe::factory()->for($user->currentTeam)->create(['name' => "Recipe {$day}", 'created_at' => now()->subDays($day)]);
    }
    Recipe::factory()->create(['name' => 'Someone else']);

    $component = Livewire::actingAs($user)->test('dashboard.recipes');

    expect($component->get('recipes')->pluck('name')->all())
        ->toBe(['Recipe 1', 'Recipe 2', 'Recipe 3', 'Recipe 4', 'Recipe 5']);

    $component->assertDontSee('Oldest')->assertDontSee('Someone else');
});

test('it prompts to add a recipe when there are none', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('dashboard.recipes')
        ->assertSee(__('Add a recipe'));
});
