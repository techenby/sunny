<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_drafts', function (Blueprint $table): void {
            $table->id();
            $table->string('server', 64);
            $table->unsignedBigInteger('team_id');
            $table->bigInteger('parent_id')->nullable();
            $table->string('batch')->nullable();
            $table->string('category')->nullable();
            $table->json('candidates');
            $table->unsignedInteger('next_candidate_id')->default(1);
            $table->timestamps();
            $table->unique(['server', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_drafts');
    }
};
