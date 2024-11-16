<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('can view component', function () {
    User::factory()->create(['name' => 'Luffy D. Monkey', 'email' => 'captain@strawhats.pirate']);

    Volt::test('tiles.status', ['position' => 'a1', 'email' => 'captain@strawhats.pirate'])
        ->assertSee('Luffy')
        ->assertSee('Status')
        ->assertSee('Cleared');
});
