<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\TeamInvitation;
use App\Models\User;
use Bentonow\BentoLaravel\Facades\Bento;
use Illuminate\Support\Facades\Http;

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

test('registration in production tracks the new user in bento', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['bentonow.secret_key' => 'secret']);
    Http::fake();

    Bento::shouldReceive('track')
        ->once()
        ->withArgs(fn (string $email, string $type): bool => $email === 'nami@example.com' && $type === '$completed_onboarding');

    $user = resolve(CreateNewUser::class)->create([
        'name' => 'Nami',
        'email' => 'nami@example.com',
        'password' => 'Sunny-Home-2026!',
        'password_confirmation' => 'Sunny-Home-2026!',
    ]);

    expect($user->exists)->toBeTrue();
});

test('registration in production skips bento when it is not configured', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['bentonow.secret_key' => null]);
    Http::fake();

    Bento::shouldReceive('track')->never();

    $user = resolve(CreateNewUser::class)->create([
        'name' => 'Nico Robin',
        'email' => 'robin@example.com',
        'password' => 'Sunny-Home-2026!',
        'password_confirmation' => 'Sunny-Home-2026!',
    ]);

    expect($user->exists)->toBeTrue();
});
