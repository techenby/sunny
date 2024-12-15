<?php

use App\Livewire\Pages\Cookbook\Recipes;
use App\Models\User;
use Livewire\Livewire;

test('can view page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/cookbook/recipes')
        ->assertOk()
        ->assertSee('Recipes');
});

test('can view component', function () {
    Livewire::test(Recipes::class)
        ->assertSee('Recipes');
});
