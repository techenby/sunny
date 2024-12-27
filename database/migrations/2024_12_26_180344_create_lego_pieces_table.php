<?php

use App\Models\LegoGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lego_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(LegoGroup::class, 'group_id');
            $table->string('name');
            $table->string('part_number')->unique();
            $table->string('image');
            $table->string('href');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lego_pieces');
    }
};
