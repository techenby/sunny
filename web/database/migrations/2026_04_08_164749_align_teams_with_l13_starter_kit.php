<?php

use App\Enums\TeamRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add slug, is_personal, soft deletes to teams
        Schema::table('teams', function (Blueprint $table) {
            $table->string('slug')->unique()->after('name')->nullable();
            $table->boolean('is_personal')->default(false)->after('slug');
            $table->softDeletes();
        });

        // Backfill slugs
        DB::table('teams')->orderBy('id')->each(function ($team) {
            $slug = Str::slug($team->name);
            $suffix = 0;

            while (DB::table('teams')->where('slug', $suffix === 0 ? $slug : $slug . '-' . $suffix)->where('id', '!=', $team->id)->exists()) {
                $suffix++;
            }

            DB::table('teams')->where('id', $team->id)->update([
                'slug' => $suffix === 0 ? $slug : $slug . '-' . $suffix,
            ]);
        });

        // 2. Rename team_user to team_members and add role column
        Schema::rename('team_user', 'team_members');

        Schema::table('team_members', function (Blueprint $table) {
            $table->string('role')->default(TeamRole::Member->value)->after('user_id');
        });

        // Backfill roles: set owner for rows matching teams.user_id
        DB::table('team_members')
            ->join('teams', 'team_members.team_id', '=', 'teams.id')
            ->where('team_members.user_id', DB::raw('teams.user_id'))
            ->update(['team_members.role' => TeamRole::Owner->value]);

        // 3. Drop user_id from teams (ownership now in pivot)
        Schema::table('teams', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        // 4. Restructure team_invitations
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->string('code', 64)->unique()->after('id')->nullable();
            $table->string('role')->default(TeamRole::Member->value)->after('email');
            $table->foreignId('invited_by')->nullable()->after('role')->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->after('invited_by');
            $table->timestamp('accepted_at')->nullable()->after('expires_at');
        });

        // Backfill codes for existing invitationsf
        DB::table('team_invitations')->orderBy('id')->each(function ($invitation) {
            DB::table('team_invitations')->where('id', $invitation->id)->update([
                'code' => Str::random(64),
            ]);
        });

        Schema::table('users', function ($table) {
            $table->dropForeign(['current_team_id']);
            $table->foreign('current_team_id')->references('id')->on('teams')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Reverse team_invitations changes
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->dropColumn(['code', 'role', 'expires_at', 'accepted_at']);
            $table->dropForeign(['invited_by']);
            $table->dropColumn('invited_by');
        });

        // Re-add user_id to teams from pivot owner
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained();
        });

        // Backfill user_id from team_members owner role
        DB::table('teams')
            ->join('team_members', function ($join) {
                $join->on('teams.id', '=', 'team_members.team_id')
                    ->where('team_members.role', '=', TeamRole::Owner->value);
            })
            ->update(['teams.user_id' => DB::raw('team_members.user_id')]);

        // Drop role from team_members and rename back
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::rename('team_members', 'team_user');

        // Drop slug, is_personal, soft deletes from teams
        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'is_personal']);
            $table->dropSoftDeletes();
        });
    }
};
