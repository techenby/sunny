<?php

use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test('pages::kiosk.configure')
        ->assertStatus(200);
});
