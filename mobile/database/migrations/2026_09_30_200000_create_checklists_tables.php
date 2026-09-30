<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklists', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('server', 64)->index();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type');
            $table->string('name');
            $table->bigInteger('local_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('server', 64)->index();
            $table->bigInteger('checklist_id')->index();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->bigInteger('local_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('sunny_pending_writes', fn (Blueprint $table) => $table->boolean('deletes')->default(false));
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklists');
        Schema::table('sunny_pending_writes', fn (Blueprint $table) => $table->dropColumn('deletes'));
    }
};
