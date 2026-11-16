<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunny_onboarding', function (Blueprint $table): void {
            $table->id();
            $table->string('server', 64);
            $table->unsignedBigInteger('user_id');
            $table->string('first_name')->nullable();
            $table->timestamp('set_up_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();
            $table->unique(['server', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunny_onboarding');
    }
};
