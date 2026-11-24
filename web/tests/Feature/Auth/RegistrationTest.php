<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\TeamInvitation;
use App\Models\User;
use Bentonow\BentoLaravel\Facades\Bento;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

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

test('someone who registers with a pending invitation can accept it once they verify their email, which joins the team and deletes it', function () {
    $luffy = User::factory()->create();
    $invitation = TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'Zoro@Example.com']);

    $this->post(route('register.store'), [
        'name' => 'Roronoa Zoro',
        'email' => 'zoro@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'zoro@example.com')->sole();

    Livewire::actingAs($user)
        ->test('pages::teams.pending-invitations-modal')
        ->call('acceptInvitation', $invitation->code)
        ->assertHasErrors('invitation');

    $user->markEmailAsVerified();

    Livewire::actingAs($user)
        ->test('pages::teams.pending-invitations-modal')
        ->call('acceptInvitation', $invitation->code)
        ->assertHasNoErrors();

    expect($user->fresh()->belongsToTeam($luffy->currentTeam))->toBeTrue()
        ->and($invitation->fresh())->toBeNull();
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

test('the sign-up form stops repeated tries from one network', function () {
    $this->freezeSecond();

    foreach (range(1, 6) as $attempt) {
        $this->post(route('register.store'), ['name' => 'Nami'])->assertSessionHasErrors('password');
    }

    $this->post(route('register.store'), [
        'name' => 'Nami',
        'email' => 'nami@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors(['email' => 'Too many attempts. Please try again in 60 seconds.']);

    $this->assertGuest();
    expect(User::where('email', 'nami@example.com')->exists())->toBeFalse();
});

test('one network can only create so many accounts in a day', function () {
    $register = fn (int $number): User => resolve(CreateNewUser::class)->create([
        'name' => "Pirate {$number}",
        'email' => "pirate{$number}@example.com",
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    foreach (range(1, CreateNewUser::ACCOUNTS_PER_NETWORK_PER_DAY) as $number) {
        $register($number);
    }

    expect(fn () => $register(11))->toThrow(ValidationException::class, 'Too many accounts have been created from this network today.');
    expect(User::where('email', 'pirate11@example.com')->exists())->toBeFalse();

    $this->travel(1)->days();

    expect($register(11)->email)->toBe('pirate11@example.com');
});

test('the last account a network may create today cannot be claimed twice at once', function () {
    $register = fn (string $name): User => resolve(CreateNewUser::class)->create([
        'name' => $name,
        'email' => "{$name}@example.com",
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    foreach (range(1, CreateNewUser::ACCOUNTS_PER_NETWORK_PER_DAY - 1) as $number) {
        $register("pirate{$number}");
    }

    $refused = null;

    User::creating(function (User $user) use ($register, &$refused): void {
        if ($user->email !== 'last@example.com') {
            return;
        }

        try {
            $register('racer');
        } catch (ValidationException $exception) {
            $refused = $exception;
        }
    });

    $register('last');

    expect($refused?->errors()['email'][0])->toStartWith('Too many accounts have been created from this network today.')
        ->and(User::where('email', 'last@example.com')->exists())->toBeTrue()
        ->and(User::where('email', 'racer@example.com')->exists())->toBeFalse();
});
