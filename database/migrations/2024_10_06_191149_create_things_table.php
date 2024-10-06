<?php

use App\Models\Bin;
use App\Models\Location;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('things', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Location::class)->nullable();
            $table->foreignIdFor(Bin::class)->nullable();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('things');
    }
};
