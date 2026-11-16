<?php

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
