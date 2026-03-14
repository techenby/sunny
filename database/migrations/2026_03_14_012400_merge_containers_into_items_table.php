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

        // Migrate containers into items
        DB::statement('
            INSERT INTO items (id, team_id, type, parent_id, name, created_at, updated_at)
            SELECT id + (SELECT COALESCE(MAX(id), 0) FROM items),
                   team_id, type, parent_id, name, created_at, updated_at
            FROM containers
        ');

        // Build a mapping of old container IDs to new item IDs
        $offset = DB::table('items')->whereNull('type')->max('id') ?? 0;

        // Update parent_id references for migrated containers (self-references)
        DB::statement("
            UPDATE items
            SET parent_id = parent_id + {$offset}
            WHERE type IS NOT NULL AND parent_id IS NOT NULL
        ");

        // Update existing items' container_id to point to the new item IDs
        DB::statement("
            UPDATE items
            SET parent_id = container_id + {$offset}
            WHERE container_id IS NOT NULL
        ");

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['container_id']);
            $table->dropColumn('container_id');
            $table->foreign('parent_id')->references('id')->on('items')->nullOnDelete();
            $table->index(['team_id', 'type']);
            $table->index(['team_id', 'parent_id']);
        });

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

        // Move container-type items back to containers table
        DB::statement('
            INSERT INTO containers (team_id, type, parent_id, name, created_at, updated_at)
            SELECT team_id, type, parent_id, name, created_at, updated_at
            FROM items WHERE type IS NOT NULL
        ');

        // Delete container items from items table
        DB::table('items')->whereNotNull('type')->delete();

        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['team_id', 'type']);
            $table->dropIndex(['team_id', 'parent_id']);
            $table->dropColumn(['parent_id', 'type']);
        });
    }
};
