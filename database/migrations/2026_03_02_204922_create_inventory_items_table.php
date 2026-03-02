<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('inventory_items')->nullOnDelete();
            $table->string('type');
            $table->string('name');
            $table->timestamps();

            $table->index(['team_id', 'parent_id']);
            $table->index(['team_id', 'type']);
        });

        // Migrate containers
        DB::statement('
            INSERT INTO inventory_items (id, team_id, parent_id, type, name, created_at, updated_at)
            SELECT id, team_id, parent_id, type, name, created_at, updated_at
            FROM containers
        ');

        // Get max container ID to offset item IDs
        $maxContainerId = DB::table('containers')->max('id') ?? 0;

        // Migrate items with offset IDs and mapped parent_id
        DB::statement("
            INSERT INTO inventory_items (id, team_id, parent_id, type, name, created_at, updated_at)
            SELECT id + {$maxContainerId}, team_id, container_id, 'item', name, created_at, updated_at
            FROM items
        ");

        Schema::dropIfExists('items');
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
            $table->string('category')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'parent_id']);
            $table->index(['team_id', 'type']);
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('container_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index('team_id');
        });

        Schema::dropIfExists('inventory_items');
    }
};
