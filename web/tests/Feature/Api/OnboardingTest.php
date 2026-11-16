<?php

use App\Actions\Onboarding\CompleteOnboarding;
use App\Enums\ChecklistType;
use App\Models\User;

test('guests cannot mark onboarding as done', function () {
    $this->postJson(route('api.user.onboarding'))->assertUnauthorized();
});

test('the user endpoint shows when onboarding has not happened yet', function () {
    $this->actingAs(User::factory()->needsOnboarding()->create())
        ->getJson(route('api.user'))
        ->assertJsonPath('data.onboarded_at', null);
});

test('a user can mark onboarding as done', function () {
    $this->freezeSecond();
    $user = User::factory()->needsOnboarding()->create();

    $this->actingAs($user)
        ->postJson(route('api.user.onboarding'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.onboarded_at', now()->toJSON());

    expect($user->fresh()->onboarded_at->equalTo(now()))->toBeTrue();
});

test('marking onboarding as done again keeps the first time', function () {
    $user = User::factory()->create(['onboarded_at' => now()->subWeek()]);
    $onboardedAt = $user->onboarded_at;

    $this->actingAs($user)
        ->postJson(route('api.user.onboarding'))
        ->assertOk()
        ->assertJsonPath('data.onboarded_at', $onboardedAt->toJSON());

    expect($user->fresh()->onboarded_at->equalTo($onboardedAt))->toBeTrue();
});

test('marking onboarding as done gives the user their getting-started list once', function () {
    $user = User::factory()->needsOnboarding()->create();
    $team = $user->currentTeam;

    $this->actingAs($user)->postJson(route('api.user.onboarding'))->assertOk();
    $this->actingAs($user)->postJson(route('api.user.onboarding'))->assertOk();

    $list = $team->checklists()->sole();

    expect($list->name)->toBe(CompleteOnboarding::LIST_NAME)
        ->and($list->user_id)->toBe($user->id)
        ->and($list->type)->toBe(ChecklistType::Todo)
        ->and($list->items()->orderBy('position')->pluck('url', 'name')->all())->toBe([
            'Set up your home' => null,
            'Put something away' => route('inventory.index', ['current_team' => $team->slug]),
            'Save a recipe' => route('recipes.create', ['current_team' => $team->slug]),
            'Invite your family' => route('teams.edit', $team->slug),
        ]);
});

test('users who were already onboarded get no list from the endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('api.user.onboarding'))->assertOk();

    expect($user->currentTeam->checklists()->exists())->toBeFalse();
});
