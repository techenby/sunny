<?php

use App\Actions\Inventory\MoveItemToTeam;
use App\Models\Item;
use App\Models\Team;

test('it takes everything inside along, keeping the nesting', function () {
    $tote = Item::factory()->bin()->create();
    $bag = Item::factory()->bin()->childOf($tote)->create();
    $hammer = Item::factory()->item()->childOf($bag)->create();
    $cabin = Team::factory()->create();

    (new MoveItemToTeam)->handle($tote, $cabin);

    expect($bag->fresh())
        ->team_id->toBe($cabin->id)
        ->parent_id->toBe($tote->id);

    expect($hammer->fresh())
        ->team_id->toBe($cabin->id)
        ->parent_id->toBe($bag->id);
});

test('it takes deleted contents along so they restore in the new team', function () {
    $tote = Item::factory()->bin()->create();
    $hammer = Item::factory()->item()->childOf($tote)->create();
    $hammer->delete();
    $cabin = Team::factory()->create();

    (new MoveItemToTeam)->handle($tote, $cabin);

    expect(Item::withTrashed()->find($hammer->id))
        ->team_id->toBe($cabin->id);
});

test('it leaves the old parent and its other contents in the old team', function () {
    $home = Team::factory()->create();
    $garage = Item::factory()->location()->for($home)->create();
    $tote = Item::factory()->bin()->childOf($garage)->create();
    $shovel = Item::factory()->item()->childOf($garage)->create();
    $cabin = Team::factory()->create();

    (new MoveItemToTeam)->handle($tote, $cabin);

    expect($garage->fresh()->team_id)->toBe($home->id);
    expect($shovel->fresh()->team_id)->toBe($home->id);
});
