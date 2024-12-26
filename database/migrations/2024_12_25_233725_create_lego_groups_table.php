<?php

use App\Models\LegoGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lego_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(LegoGroup::class, 'parent_id')->nullable();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('has_pieces')->default(false);
            $table->string('href');
            $table->string('summary');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lego_groups');
    }
};
