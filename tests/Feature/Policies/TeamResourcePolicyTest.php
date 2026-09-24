<?php

use App\Enums\TeamRole;
use App\Models\CalendarFeed;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Routine;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

dataset('team resources', [
    'item' => fn (Team $team): Model => Item::factory()->for($team)->create(),
    'recipe' => fn (Team $team): Model => Recipe::factory()->for($team)->create(),
    'routine' => fn (Team $team): Model => Routine::factory()->for($team)->create(),
    'checklist' => fn (Team $team): Model => Checklist::factory()->for($team)->create(),
    'checklist item' => fn (Team $team): Model => ChecklistItem::factory()->for(Checklist::factory()->for($team))->create(),
    'calendar feed' => fn (Team $team): Model => CalendarFeed::factory()->for($team)->create(),
]);

test('members can access resources on any of their teams', function (Closure $makeResource) {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $resource = $makeResource($team);

    expect(Gate::forUser($user)->allows('view', $resource))->toBeTrue()
        ->and(Gate::forUser($user)->allows('update', $resource))->toBeTrue()
        ->and(Gate::forUser($user)->allows('delete', $resource))->toBeTrue()
        ->and($user->current_team_id)->not->toBe($team->id);
})->with('team resources');

test('non-members cannot access resources', function (Closure $makeResource) {
    $user = User::factory()->create();

    $resource = $makeResource(Team::factory()->create());

    expect(Gate::forUser($user)->allows('view', $resource))->toBeFalse()
        ->and(Gate::forUser($user)->allows('update', $resource))->toBeFalse()
        ->and(Gate::forUser($user)->allows('delete', $resource))->toBeFalse();
})->with('team resources');
