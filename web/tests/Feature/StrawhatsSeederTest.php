<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Models\CalendarFeed;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\Membership;
use App\Models\Recipe;
use App\Models\Routine;
use App\Models\RoutineOccurrenceStep;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Database\Seeders\StrawhatsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
    $this->seed(StrawhatsSeeder::class);
    $this->strawhats = Team::where('slug', 'strawhat-pirates')->firstOrFail();
    $this->nami = User::where('email', 'nami@strawhat.pirates')->firstOrFail();
});

it('gives Nami an admin seat, an empty personal team and a second team', function () {
    expect($this->nami->currentTeam->is($this->strawhats))->toBeTrue()
        ->and(Membership::where('team_id', $this->strawhats->id)->where('user_id', $this->nami->id)->value('role'))->toBe(TeamRole::Admin)
        ->and($this->nami->teams()->pluck('name')->all())->toContain('Baratie', "Nami's Team");

    $personal = $this->nami->teams()->where('name', "Nami's Team")->firstOrFail();

    expect($personal->items()->count() + $personal->recipes()->count() + $personal->routines()->count())->toBe(0);
});

it('stocks containers, photos, details and a recently deleted thing', function () {
    expect(Item::where('team_id', $this->strawhats->id)->where('type', 'bin')->count())->toBeGreaterThanOrEqual(4)
        ->and(Item::where('name', 'Log Pose')->firstOrFail()->metadata)->toHaveKey('Notes')
        ->and(Item::onlyTrashed()->where('name', 'Cracked Den Den Mushi')->exists())->toBeTrue()
        ->and(Item::where('team_id', $this->strawhats->id)->whereNull('parent_id')->where('type', 'item')->count())->toBe(2);

    ItemPhoto::all()->each(fn (ItemPhoto $photo) => Storage::assertExists($photo->path));
});

it('gives some recipes photos and shares one', function () {
    expect(Recipe::where('team_id', $this->strawhats->id)->whereNotNull('photo_path')->count())->toBe(4)
        ->and(Recipe::where('name', "Luffy's Favorite Meat on the Bone")->firstOrFail()->isShared())->toBeTrue();
});

it('stores calendars with events and leaves one failing', function () {
    $feeds = CalendarFeed::where('team_id', $this->strawhats->id)->get();

    expect($feeds->filter->isFailing()->pluck('name')->all())->toBe(['Marine Patrols'])
        ->and($feeds->reject->isFailing()->every(fn (CalendarFeed $feed) => str_contains((string) $feed->storedIcs(), 'BEGIN:VEVENT')))->toBeTrue();
});

it('covers the routine, list and team states the app shows', function () {
    expect(Routine::where('name', 'Lookout Duty')->firstOrFail()->steps()->count())->toBe(0)
        ->and(RoutineOccurrenceStep::whereNotNull('completed_at')->count())->toBe(2)
        ->and(Checklist::where('name', 'Treasure To Find')->firstOrFail()->items()->count())->toBe(0)
        ->and(ChecklistItem::whereNotNull('url')->exists())->toBeTrue()
        ->and(TeamInvitation::where('team_id', $this->strawhats->id)->count())->toBe(1);
});
