<?php

use App\Models\User;

it('displays the landing page for guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Your household, organized')
        ->assertSee('Recipes')
        ->assertSee('Inventory')
        ->assertSee('Teams')
        ->assertSee('Log in')
        ->assertSee('Register')
        ->assertSee('Coming Soon')
        ->assertSee('Collections')
        ->assertSee('Budgeting');
});

it('shows dashboard link for authenticated users', function () {
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Dashboard');
});
