<?php

use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test('pages::kiosk.calendar')
        ->assertStatus(200);
});
