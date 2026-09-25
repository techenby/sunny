<?php

use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('new users with a single name can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Shane',
        'email' => 'shane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('new users get a team on registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Monkey D. Luffy',
        'email' => 'luffy@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'luffy@example.com')->first();

    expect($user->ownedTeams)->toHaveCount(1)
        ->and($user->teams)->toHaveCount(1)
        ->and($user->teams->first()->name)->toBe("Monkey D. Luffy's Team")
        ->and($user->current_team_id)->toBe($user->teams->first()->id);
});
