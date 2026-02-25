<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('containers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('containers')->nullOnDelete();
            $table->string('type');
            $table->string('name');
            $table->string('category')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'type']);
            $table->index(['team_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('containers');
    }
};
