<?php

use App\Models\User;
use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::kiosk.routines')
        ->assertStatus(200)
        ->assertSee('Routines');
});
