<?php

use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('renders successfully', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('kiosk.chore-chart'))
        ->assertOk();

    Livewire::actingAs($user)
        ->test('pages::kiosk.chore-chart')
        ->assertOk();
})->group('smoke');
