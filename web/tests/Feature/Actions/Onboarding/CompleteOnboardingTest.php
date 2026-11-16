<?php

use App\Actions\Onboarding\CompleteOnboarding;
use App\Enums\ChecklistType;
use App\Models\Item;
use App\Models\User;

test('it marks the user onboarded and gives them a personal getting-started list', function () {
    $this->freezeSecond();
    $user = User::factory()->needsOnboarding()->create();
    $team = $user->currentTeam;

    expect(resolve(CompleteOnboarding::class)->handle($user))->toBeTrue();

    $list = $team->checklists()->sole();

    expect($user->fresh()->onboarded_at->equalTo(now()))->toBeTrue()
        ->and($list->name)->toBe('Get started with SunnyHome')
        ->and($list->type)->toBe(ChecklistType::Todo)
        ->and($list->user_id)->toBe($user->id)
        ->and($list->items()->orderBy('position')->get(['name', 'url'])->toArray())->toBe([
            ['name' => 'Set up your home', 'url' => null],
            ['name' => 'Put something away', 'url' => route('inventory.index', ['current_team' => $team->slug])],
            ['name' => 'Save a recipe', 'url' => route('recipes.create', ['current_team' => $team->slug])],
            ['name' => 'Invite your family', 'url' => route('teams.edit', $team->slug)],
        ]);
});

test('set up your home starts ticked and the rest start open', function () {
    $user = User::factory()->needsOnboarding()->create();

    resolve(CompleteOnboarding::class)->handle($user);

    $items = $user->currentTeam->checklists()->sole()->items()->orderBy('position')->get();

    expect($items->first()->isCompleted())->toBeTrue()
        ->and($items->first()->completed_by)->toBe($user->id)
        ->and($items->skip(1)->every(fn ($item) => ! $item->isCompleted()))->toBeTrue();
});

test('put something away points at the first place in the home', function () {
    $user = User::factory()->needsOnboarding()->create();
    $team = $user->currentTeam;
    $garage = Item::factory()->for($team)->location()->create(['name' => 'Garage']);
    Item::factory()->for($team)->location()->create(['name' => 'Shed']);

    resolve(CompleteOnboarding::class)->handle($user);

    expect($team->checklists()->sole()->items()->where('name', 'Put something away')->value('url'))
        ->toBe(route('inventory.index', ['current_team' => $team->slug, 'parentId' => $garage->id]));
});

test('calling it again does not make a second list', function () {
    $user = User::factory()->needsOnboarding()->create();

    resolve(CompleteOnboarding::class)->handle($user);

    expect(resolve(CompleteOnboarding::class)->handle($user))->toBeFalse()
        ->and($user->currentTeam->checklists()->count())->toBe(1);
});

test('a stale user that another request already onboarded gets no list', function () {
    $user = User::factory()->needsOnboarding()->create();
    User::whereKey($user->id)->update(['onboarded_at' => now()]);

    expect(resolve(CompleteOnboarding::class)->handle($user))->toBeFalse()
        ->and($user->currentTeam->checklists()->exists())->toBeFalse();
});

test('existing users get no list', function () {
    $user = User::factory()->create();

    expect(resolve(CompleteOnboarding::class)->handle($user))->toBeFalse()
        ->and($user->currentTeam->checklists()->exists())->toBeFalse();
});

test('deleting the list does not bring it back', function () {
    $user = User::factory()->needsOnboarding()->create();

    resolve(CompleteOnboarding::class)->handle($user);
    $user->currentTeam->checklists()->sole()->delete();
    resolve(CompleteOnboarding::class)->handle($user);

    expect($user->currentTeam->checklists()->exists())->toBeFalse();
});
