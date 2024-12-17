<?php

use App\Livewire\Pages\Cookbook\EditRecipe;
use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test(EditRecipe::class)
        ->assertStatus(200);
});
