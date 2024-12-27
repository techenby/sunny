<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lego_colors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('hex');
            $table->boolean('is_trans')->default(false);
            $table->json('external')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lego_colors');
    }
};
