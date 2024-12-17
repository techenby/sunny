<?php

use App\Livewire\Pages\Cookbook\Recipes;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Livewire;

test('can view page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/cookbook/recipes')
        ->assertOk()
        ->assertSee('Recipes');
});

test('can view component', function () {
    Recipe::factory()
        ->count(3)
        ->state(new Sequence(
            ['name' => 'Mac & Cheese'],
            ['name' => 'Nut Cups'],
            ['name' => 'Falafel'],
        ))
        ->create();

    Livewire::test(Recipes::class)
        ->assertOk()
        ->assertSee('Recipes')
        ->assertSee(['Mac & Cheese', 'Nut Cups', 'Falafel']);
});