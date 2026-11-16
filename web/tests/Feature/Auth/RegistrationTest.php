<?php

use App\Models\TeamInvitation;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('registration screen shows the team from a pending invitation', function () {
    $luffy = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create();

    $this->get(route('register', ['invitation' => $invitation->code]))
        ->assertOk()
        ->assertSeeHtml('data-test="team-invitation-alert"')
        ->assertSee($luffy->currentTeam->name)
        ->assertSee(route('login', ['invitation' => $invitation->code]));
});

test('new users can register', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors()
        ->assertRedirect(route('onboarding', absolute: false));

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
        ->assertRedirect(route('onboarding', absolute: false));

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

test('new users start out needing onboarding', function () {
    $this->post(route('register.store'), [
        'name' => 'Nami',
        'email' => 'nami@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'nami@example.com')->sole();

    expect($user->needsOnboarding())->toBeTrue();
});

test('people who register with a pending invitation skip onboarding', function () {
    $luffy = User::factory()->create();
    TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'Zoro@Example.com']);

    $this->post(route('register.store'), [
        'name' => 'Roronoa Zoro',
        'email' => 'zoro@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'zoro@example.com')->sole();

    expect($user->needsOnboarding())->toBeFalse()
        ->and($user->currentTeam->checklists()->exists())->toBeFalse();
});

test('an expired invitation does not skip onboarding', function () {
    $luffy = User::factory()->create();
    TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'sanji@example.com', 'expires_at' => now()->subDay()]);

    $this->post(route('register.store'), [
        'name' => 'Sanji',
        'email' => 'sanji@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('onboarding', absolute: false));
});
