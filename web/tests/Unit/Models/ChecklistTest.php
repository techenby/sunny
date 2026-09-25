<?php

use App\Enums\ChecklistType;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Team;
use App\Models\User;

test('a checklist belongs to a team', function () {
    $team = Team::factory()->create();
    $checklist = Checklist::factory()->for($team)->create();

    expect($checklist->team->id)->toBe($team->id)
        ->and($team->checklists)->toHaveOne();
});

test('items are ordered by position', function () {
    $checklist = Checklist::factory()->create();

    ChecklistItem::factory()->for($checklist)->create(['name' => 'Third', 'position' => 3]);
    ChecklistItem::factory()->for($checklist)->create(['name' => 'First', 'position' => 1]);
    ChecklistItem::factory()->for($checklist)->create(['name' => 'Second', 'position' => 2]);

    expect($checklist->items->pluck('name')->all())->toBe(['First', 'Second', 'Third']);
});

test('position is auto-assigned to the end of the list', function () {
    $checklist = Checklist::factory()->create();

    $first = ChecklistItem::factory()->for($checklist)->create();
    $second = ChecklistItem::factory()->for($checklist)->create();

    expect($first->position)->toBe(1)
        ->and($second->position)->toBe(2);
});

test('position is auto-assigned per checklist', function () {
    $checklist = Checklist::factory()->create();
    $other = Checklist::factory()->create();

    ChecklistItem::factory()->for($checklist)->create();
    $item = ChecklistItem::factory()->for($other)->create();

    expect($item->position)->toBe(1);
});

test('an explicit position is respected', function () {
    $checklist = Checklist::factory()->create();

    $item = ChecklistItem::factory()->for($checklist)->create(['position' => 10]);

    expect($item->position)->toBe(10);
});

test('completing an item records who completed it', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->create();

    $item->complete($user);

    expect($item->isCompleted())->toBeTrue()
        ->and($item->completedBy->id)->toBe($user->id);
});

test('toggling an item flips its completed state', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->create();

    $item->toggle($user);
    expect($item->isCompleted())->toBeTrue();

    $item->toggle($user);
    expect($item->isCompleted())->toBeFalse()
        ->and($item->completed_by)->toBeNull();
});

test('progress reports the percentage of completed items', function () {
    $checklist = Checklist::factory()->create();

    ChecklistItem::factory()->for($checklist)->count(3)->create();
    ChecklistItem::factory()->for($checklist)->completed()->create();

    expect($checklist->progress())->toBe(25);
});

test('progress is zero for an empty checklist', function () {
    expect(Checklist::factory()->create()->progress())->toBe(0);
});

test('a checklist is complete once every item is checked', function () {
    $checklist = Checklist::factory()->create();
    $item = ChecklistItem::factory()->for($checklist)->create();

    expect($checklist->isComplete())->toBeFalse();

    $item->complete();

    expect($checklist->isComplete())->toBeTrue();
});

test('an empty checklist is not complete', function () {
    expect(Checklist::factory()->create()->isComplete())->toBeFalse();
});

test('resetting a checklist unchecks every item', function () {
    $checklist = Checklist::factory()->shopping()->create();
    ChecklistItem::factory()->for($checklist)->completed(User::factory()->create())->count(2)->create();

    $checklist->reset();

    expect($checklist->items()->completed()->count())->toBe(0)
        ->and($checklist->items()->whereNotNull('completed_by')->count())->toBe(0);
});

test('completed and incomplete scopes filter items', function () {
    $checklist = Checklist::factory()->create();
    ChecklistItem::factory()->for($checklist)->count(2)->create();
    ChecklistItem::factory()->for($checklist)->completed()->create();

    expect($checklist->items()->completed()->count())->toBe(1)
        ->and($checklist->items()->incomplete()->count())->toBe(2);
});

test('the type scope filters checklists', function () {
    Checklist::factory()->shopping()->count(2)->create();
    Checklist::factory()->wishlist()->create();

    expect(Checklist::ofType(ChecklistType::Shopping)->count())->toBe(2);
});

test('the type is cast to an enum', function () {
    expect(Checklist::factory()->wishlist()->create()->type)->toBe(ChecklistType::Wishlist);
});

test('a list can belong to a team member', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->wishlist()->ownedBy($user)->create();

    expect($checklist->user->id)->toBe($user->id);
});

test('the household scope finds unowned lists', function () {
    Checklist::factory()->ownedBy(User::factory()->create())->create();
    Checklist::factory()->household()->count(2)->create();

    expect(Checklist::household()->count())->toBe(2);
});

test('deleting a checklist cascades to its items', function () {
    $checklist = Checklist::factory()->create();
    ChecklistItem::factory()->for($checklist)->count(2)->create();

    $checklist->forceDelete();

    expect(ChecklistItem::count())->toBe(0);
});

test('a checklist is soft deleted', function () {
    $checklist = Checklist::factory()->create();

    $checklist->delete();

    expect($checklist)->toBeTrashed()
        ->and(Checklist::count())->toBe(0);
});
