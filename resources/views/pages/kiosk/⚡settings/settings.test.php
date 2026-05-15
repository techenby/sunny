<?php

use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test('pages::kiosk.settings')
        ->assertStatus(200);
});
