<?php

use App\Models\User;
use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::kiosk.chore-chart')
        ->assertStatus(200)
        ->assertSee('Chore Chart');
});
