<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'update items set parent_id = null where parent_id = id or exists ('
            . 'select 1 from items as places where places.id = items.parent_id and places.team_id <> items.team_id'
            . ')'
        );

        Schema::table('items', function (Blueprint $table) {
            $table->unique(['id', 'team_id']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreign(['parent_id', 'team_id'])->references(['id', 'team_id'])->on('items');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table items add constraint items_parent_id_not_self check (parent_id <> id)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('alter table items drop constraint items_parent_id_not_self');
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['parent_id', 'team_id']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique(['id', 'team_id']);
        });
    }
};
