<?php

use App\Enums\TeamRole;
use App\Models\Checklist;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Routine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

test('purging a user really deletes the teams only they use', function () {
    Storage::fake();
    $ace = User::factory()->create(['name' => 'Portgas D. Ace']);
    $spade = $ace->currentTeam;
    Storage::put('items/hat.jpg', 'photo');
    Storage::put('items/hat-thumb.jpg', 'thumb');
    Storage::put('recipes/stew.jpg', 'photo');
    $item = Item::factory()->for($spade)->create(['photo_path' => 'items/hat.jpg', 'thumb_path' => 'items/hat-thumb.jpg']);
    $recipe = Recipe::factory()->for($spade)->create(['photo_path' => 'recipes/stew.jpg']);
    $trashed = Recipe::factory()->for($spade)->create();
    $trashed->delete();
    $checklist = Checklist::factory()->for($spade)->create();
    $routine = Routine::factory()->for($spade)->create();
    $ace->createToken('iPhone');

    $ace->purge();

    expect($ace->fresh())->toBeNull()
        ->and(Team::withTrashed()->find($spade->id))->toBeNull()
        ->and(Item::withTrashed()->find($item->id))->toBeNull()
        ->and(Recipe::withTrashed()->whereKey([$recipe->id, $trashed->id])->count())->toBe(0)
        ->and(Checklist::withTrashed()->find($checklist->id))->toBeNull()
        ->and(Routine::withTrashed()->find($routine->id))->toBeNull()
        ->and(DB::table('team_members')->where('user_id', $ace->id)->count())->toBe(0)
        ->and(DB::table('personal_access_tokens')->where('tokenable_id', $ace->id)->count())->toBe(0)
        ->and(Storage::allFiles())->toBe([]);
});

test('purging a user leaves the teams they only belong to, minus their own lists and routines', function () {
    $newgate = User::factory()->create(['name' => 'Edward Newgate']);
    $whitebeard = $newgate->currentTeam;
    $ace = User::factory()->create(['name' => 'Portgas D. Ace']);
    $whitebeard->memberships()->create(['user_id' => $ace->id, 'role' => TeamRole::Member]);
    $shared = Checklist::factory()->for($whitebeard)->create();
    $sharedItem = Item::factory()->for($whitebeard)->create();
    $ownList = Checklist::factory()->for($whitebeard)->create(['user_id' => $ace->id]);
    $ownRoutine = Routine::factory()->for($whitebeard)->create(['user_id' => $ace->id]);

    $ace->purge();

    expect($whitebeard->fresh()->members->pluck('name')->all())->toBe(['Edward Newgate'])
        ->and($shared->fresh())->not->toBeNull()
        ->and($sharedItem->fresh())->not->toBeNull()
        ->and(Checklist::withTrashed()->find($ownList->id))->toBeNull()
        ->and(Routine::withTrashed()->find($ownRoutine->id))->toBeNull();
});

test('a user who owns a team other people use cannot be purged', function () {
    $ace = User::factory()->create(['name' => 'Portgas D. Ace']);
    $spade = $ace->currentTeam;
    $spade->update(['name' => 'Spade Pirates']);
    $deuce = User::factory()->create(['name' => 'Masked Deuce']);
    $spade->memberships()->create(['user_id' => $deuce->id, 'role' => TeamRole::Member]);

    expect($ace->teamsBlockingDeletion()->pluck('name')->all())->toBe(['Spade Pirates'])
        ->and($ace->deletionBlockedMessage())->toContain('Spade Pirates')
        ->and($deuce->deletionBlockedMessage())->toBeNull()
        ->and(fn () => $ace->purge())->toThrow(LogicException::class)
        ->and($ace->fresh())->not->toBeNull()
        ->and($spade->fresh()->members)->toHaveCount(2);
});

test('initials use the first and last name', function (string $name, string $initials) {
    expect(User::factory()->make(['name' => $name])->initials())->toBe($initials);
})->with([
    'three words' => ['Monkey D. Luffy', 'ML'],
    'two words' => ['nico robin', 'NR'],
    'one word' => ['Chopper', 'C'],
]);
