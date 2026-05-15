<?php

use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::test('kiosk.weather-tile')
        ->assertStatus(200);
});
