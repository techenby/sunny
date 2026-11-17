<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('accepted invitations are deleted and the column is dropped', function () {
    $migration = require database_path('migrations/2026_11_17_224003_drop_accepted_at_from_team_invitations_table.php');
    $migration->down();

    $pending = TeamInvitation::factory()->create();
    $accepted = TeamInvitation::factory()->create();
    DB::table('team_invitations')->where('id', $accepted->id)->update(['accepted_at' => now()]);

    $migration->up();

    expect(Schema::hasColumn('team_invitations', 'accepted_at'))->toBeFalse()
        ->and($pending->fresh())->not->toBeNull()
        ->and($accepted->fresh())->toBeNull();
});
