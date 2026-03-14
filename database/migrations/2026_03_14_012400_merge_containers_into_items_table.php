<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('type')->nullable()->after('team_id');
            $table->foreignId('parent_id')->nullable()->after('type');
        });

        // Create a temporary mapping table for old container IDs to new item IDs
        Schema::create('container_id_map', function (Blueprint $table) {
            $table->unsignedBigInteger('old_container_id');
            $table->unsignedBigInteger('new_item_id');
        });

        // Migrate containers into items (without specifying id, let auto-increment assign new IDs)
        $containers = DB::table('containers')->orderBy('id')->get();

        foreach ($containers as $container) {
            $newId = DB::table('items')->insertGetId([
                'team_id' => $container->team_id,
                'type' => $container->type,
                'name' => $container->name,
                'created_at' => $container->created_at,
                'updated_at' => $container->updated_at,
            ]);

            DB::table('container_id_map')->insert([
                'old_container_id' => $container->id,
                'new_item_id' => $newId,
            ]);
        }

        // Update parent_id references for migrated containers (self-references)
        DB::statement('
            UPDATE items
            SET parent_id = (
                SELECT m.new_item_id FROM container_id_map m
                WHERE m.old_container_id = (
                    SELECT c.parent_id FROM containers c
                    INNER JOIN container_id_map m2 ON m2.new_item_id = items.id
                    WHERE c.id = m2.old_container_id
                )
            )
            WHERE id IN (SELECT new_item_id FROM container_id_map)
            AND id IN (
                SELECT m3.new_item_id FROM container_id_map m3
                INNER JOIN containers c2 ON c2.id = m3.old_container_id
                WHERE c2.parent_id IS NOT NULL
            )
        ');

        // Update existing items' container_id to point to the new item IDs
        DB::statement('
            UPDATE items
            SET parent_id = (
                SELECT m.new_item_id FROM container_id_map m
                WHERE m.old_container_id = items.container_id
            )
            WHERE container_id IS NOT NULL
        ');

        // Set type to "item" for all pre-existing items
        DB::table('items')->whereNull('type')->update(['type' => 'item']);

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['container_id']);
            $table->dropColumn('container_id');
            $table->foreign('parent_id')->references('id')->on('items')->nullOnDelete();
            $table->index(['team_id', 'type']);
            $table->index(['team_id', 'parent_id']);
        });

        Schema::dropIfExists('container_id_map');
        Schema::dropIfExists('containers');
    }

    public function down(): void
    {
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('containers')->nullOnDelete();
            $table->string('type');
            $table->string('name');
            $table->timestamps();

            $table->index(['team_id', 'type']);
            $table->index(['team_id', 'parent_id']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('container_id')->nullable()->constrained()->nullOnDelete();
        });

        // Create a mapping of item IDs (containers) to new container IDs
        Schema::create('container_id_map', function (Blueprint $table) {
            $table->unsignedBigInteger('old_item_id');
            $table->unsignedBigInteger('new_container_id');
        });

        // Move container-type items back to containers table (location and bin types)
        $containerItems = DB::table('items')
            ->whereIn('type', ['location', 'bin'])
            ->orderBy('id')
            ->get();

        foreach ($containerItems as $containerItem) {
            $newId = DB::table('containers')->insertGetId([
                'team_id' => $containerItem->team_id,
                'type' => $containerItem->type,
                'name' => $containerItem->name,
                'created_at' => $containerItem->created_at,
                'updated_at' => $containerItem->updated_at,
            ]);

            DB::table('container_id_map')->insert([
                'old_item_id' => $containerItem->id,
                'new_container_id' => $newId,
            ]);
        }

        // Restore parent_id references within containers
        DB::statement('
            UPDATE containers
            SET parent_id = (
                SELECT m.new_container_id FROM container_id_map m
                WHERE m.old_item_id = (
                    SELECT i.parent_id FROM items i
                    INNER JOIN container_id_map m2 ON m2.new_container_id = containers.id
                    WHERE i.id = m2.old_item_id
                )
            )
            WHERE id IN (
                SELECT m3.new_container_id FROM container_id_map m3
                INNER JOIN items i2 ON i2.id = m3.old_item_id
                WHERE i2.parent_id IS NOT NULL
                AND i2.parent_id IN (SELECT old_item_id FROM container_id_map)
            )
        ');

        // Restore container_id on regular items by mapping parent_id to new container IDs
        DB::statement('
            UPDATE items
            SET container_id = (
                SELECT m.new_container_id FROM container_id_map m
                WHERE m.old_item_id = items.parent_id
            )
            WHERE type = \'item\'
            AND parent_id IN (SELECT old_item_id FROM container_id_map)
        ');

        // Delete container-type items from items table
        DB::table('items')->whereIn('type', ['location', 'bin'])->delete();

        Schema::dropIfExists('container_id_map');

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['team_id', 'type']);
            $table->dropIndex(['team_id', 'parent_id']);
            $table->dropColumn(['parent_id', 'type']);
        });
    }
};
