<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->boolean('is_personal')->default(false);
            $table->unsignedTinyInteger('week_start')->nullable();
            $table->json('invitations')->nullable();
            $table->timestamp('invitations_fetched_at')->nullable();
        });

        Schema::create('invitations', function (Blueprint $table): void {
            $table->string('code')->primary();
            $table->string('server', 64)->index();
            $table->string('team_name');
            $table->string('inviter_name')->nullable();
            $table->string('role');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');

        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn(['is_personal', 'week_start', 'invitations', 'invitations_fetched_at']);
        });
    }
};
