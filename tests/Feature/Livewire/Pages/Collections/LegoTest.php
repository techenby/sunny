<?php

use App\Livewire\Pages\Collections\Lego;
use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test(Lego::class)
        ->assertStatus(200);
});
