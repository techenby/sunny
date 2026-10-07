<?php

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Livewire\Livewire;

test('api tokens page is displayed', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('api-tokens.index'))
        ->assertOk();
});

test('guests are redirected to the login page', function () {
    $this->get(route('api-tokens.index'))
        ->assertRedirect(route('login'));
});

test('user can create a token', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->set('name', 'Raycast')
        ->call('createToken')
        ->assertHasNoErrors()
        ->assertSet('plainTextToken', fn (?string $token) => filled($token))
        ->assertSet('name', '');

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_id' => $user->id,
        'name' => 'Raycast',
    ]);
});

test('tokens expire after 90 days by default', function () {
    $this->freezeSecond();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->set('name', 'Raycast')
        ->call('createToken')
        ->assertHasNoErrors();

    expect($user->tokens()->sole()->expires_at->equalTo(now()->addDays(90)))->toBeTrue();
});

test('user can choose when a token expires', function (string $expiration, ?int $days) {
    $this->freezeSecond();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->set('name', 'Raycast')
        ->set('expiration', $expiration)
        ->call('createToken')
        ->assertHasNoErrors()
        ->assertSet('expiration', '90');

    expect($user->tokens()->sole()->expires_at?->toDateTimeString())
        ->toBe($days ? now()->addDays($days)->toDateTimeString() : null);
})->with([
    '30 days' => ['30', 30],
    '1 year' => ['365', 365],
    'never' => ['never', null],
]);

test('token expiration must be a known option', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.api-tokens')
        ->set('name', 'Raycast')
        ->set('expiration', '7')
        ->call('createToken')
        ->assertHasErrors('expiration');

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('expired tokens are marked as expired', function () {
    $user = User::factory()->create();
    $user->createToken('Raycast', ['*'], now()->subDay());

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->assertSee('Expired');
});

test('token name is required', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.api-tokens')
        ->set('name', '')
        ->call('createToken')
        ->assertHasErrors(['name' => 'required']);

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('user can revoke a token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Raycast')->accessToken;

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->call('revokeToken', $token->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
});

test('user cannot revoke another users token', function () {
    $otherUser = User::factory()->create();
    $otherToken = $otherUser->createToken('Raycast')->accessToken;

    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.api-tokens')
        ->call('revokeToken', $otherToken->id);

    $this->assertDatabaseHas('personal_access_tokens', ['id' => $otherToken->id]);
});

test('the expiration options come from the token lifetimes', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::settings.api-tokens')
        ->assertSeeInOrder(['30 days', '90 days', '1 year', 'Never']);
});

function connectApp(User $user, string $name): Client
{
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient(
        name: $name,
        redirectUris: ['https://example.com/callback'],
        confidential: false,
    );

    $token = Passport::token()->forceFill([
        'id' => Str::random(40),
        'user_id' => $user->id,
        'client_id' => $client->getKey(),
        'scopes' => ['mcp:use'],
        'revoked' => false,
        'expires_at' => now()->addDay(),
    ]);
    $token->save();

    Passport::refreshToken()->forceFill([
        'id' => Str::random(40),
        'access_token_id' => $token->id,
        'revoked' => false,
        'expires_at' => now()->addDays(90),
    ])->save();

    return $client;
}

test('connected oauth apps are listed', function () {
    $user = User::factory()->create();
    connectApp($user, 'Acme Assistant');
    connectApp(User::factory()->create(), 'Initech Bot');

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->assertSee('Acme Assistant')
        ->assertDontSee('Initech Bot');
});

test('user can disconnect an oauth app', function () {
    $user = User::factory()->create();
    $acme = connectApp($user, 'Acme Assistant');
    $globex = connectApp($user, 'Globex Helper');

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->call('disconnectApp', (string) $acme->getKey())
        ->assertDontSee('Acme Assistant')
        ->assertSee('Globex Helper');

    expect(Passport::token()->where('client_id', $acme->getKey())->where('revoked', false)->exists())->toBeFalse()
        ->and(Passport::refreshToken()->whereIn('access_token_id', Passport::token()->where('client_id', $acme->getKey())->pluck('id'))->where('revoked', false)->exists())->toBeFalse()
        ->and(Passport::token()->where('client_id', $globex->getKey())->where('revoked', false)->exists())->toBeTrue();
});

test('users cannot disconnect another user\'s app', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $client = connectApp($other, 'Acme Assistant');

    Livewire::actingAs($user)
        ->test('pages::settings.api-tokens')
        ->call('disconnectApp', (string) $client->getKey());

    expect(Passport::token()->where('user_id', $other->id)->where('revoked', false)->exists())->toBeTrue();
});
