<?php

use App\Models\User;
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
