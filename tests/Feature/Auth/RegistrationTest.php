<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('new users get a crew on registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Monkey D. Luffy',
        'email' => 'luffy@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'luffy@example.com')->first();

    expect($user->ownedCrews)->toHaveCount(1)
        ->and($user->ownedCrews->first()->name)->toBe("Monkey D. Luffy's Crew")
        ->and($user->current_crew_id)->toBe($user->ownedCrews->first()->id);
});
