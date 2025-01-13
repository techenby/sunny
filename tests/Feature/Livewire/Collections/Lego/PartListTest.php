<?php

use App\Livewire\Collections\Lego\PartList;
use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test(PartList::class)
        ->assertStatus(200);
});
