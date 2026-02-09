<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crew_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crew_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->timestamps();

            $table->unique(['crew_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crew_user');
    }
};
