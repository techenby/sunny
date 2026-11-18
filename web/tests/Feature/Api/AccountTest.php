<?php

use App\Enums\TeamRole;
use App\Http\Integrations\Bento\Requests\CreateDataDeletionRequest;
use App\Models\Item;
use App\Models\Team;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Saloon;

test('guests cannot delete an account', function () {
    $this->deleteJson(route('api.user.destroy'), ['password' => 'password'])->assertUnauthorized();
});

test('a user can delete their account with their password', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $item = Item::factory()->for($team)->create();
    $token = $user->createToken('iPhone')->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.user.destroy'), ['password' => 'password'])
        ->assertNoContent();

    expect($user->fresh())->toBeNull()
        ->and(PersonalAccessToken::count())->toBe(0)
        ->and(Team::withTrashed()->find($team->id))->toBeNull()
        ->and(Item::withTrashed()->find($item->id))->toBeNull();
});

test('the account is kept when the password is wrong or missing', function (array $payload) {
    $user = User::factory()->create();
    $token = $user->createToken('iPhone')->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.user.destroy'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect($user->fresh())->not->toBeNull();
})->with([
    'wrong' => [['password' => 'wrong-password']],
    'missing' => [[]],
]);

test('the account is kept while the user owns a team other people use', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['name' => 'Straw Hats']);
    $team->memberships()->create(['user_id' => User::factory()->create()->id, 'role' => TeamRole::Member]);
    $token = $user->createToken('iPhone')->plainTextToken;

    $this->withToken($token)
        ->deleteJson(route('api.user.destroy'), ['password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('teams')
        ->assertJsonPath('message', $user->deletionBlockedMessage())
        ->assertJsonPath('errors.teams.0', $user->deletionBlockedMessage())
        ->assertJsonPath('blocking_teams', [['name' => 'Straw Hats', 'slug' => $team->fresh()->slug]]);

    expect($user->fresh())->not->toBeNull()
        ->and($team->fresh()->members)->toHaveCount(2);
});

test('deleting an account in production asks bento to delete the subscriber', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['bentonow.publishable_key' => 'publishable', 'bentonow.secret_key' => 'secret', 'bentonow.site_uuid' => 'site-uuid']);
    Saloon::fake([
        CreateDataDeletionRequest::class => MockResponse::make(['status' => 'queued_for_anonymization'], 202),
    ]);
    $user = User::factory()->create(['email' => 'nami@example.com']);

    $this->actingAs($user)
        ->deleteJson(route('api.user.destroy'), ['password' => 'password'])
        ->assertNoContent();

    expect($user->fresh())->toBeNull();
    Saloon::assertSent(fn (CreateDataDeletionRequest $request): bool => $request->body()->all() === [
        'site_uuid' => 'site-uuid',
        'data_deletion_request' => ['email' => 'nami@example.com'],
    ]);
});

test('the account is still deleted when bento fails', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['bentonow.publishable_key' => 'publishable', 'bentonow.secret_key' => 'secret']);
    Saloon::fake([
        CreateDataDeletionRequest::class => MockResponse::make(['error' => 'visitor_not_found'], 404),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.user.destroy'), ['password' => 'password'])
        ->assertNoContent();

    expect($user->fresh())->toBeNull();
    Saloon::assertSent(CreateDataDeletionRequest::class);
});

test('deleting an account skips bento when it is not configured', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['bentonow.secret_key' => null]);
    Saloon::fake([]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.user.destroy'), ['password' => 'password'])
        ->assertNoContent();

    expect($user->fresh())->toBeNull();
    Saloon::assertNothingSent();
});
