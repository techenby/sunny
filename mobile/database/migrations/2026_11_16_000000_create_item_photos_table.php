<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_photos', function (Blueprint $table): void {
            $table->bigInteger('id')->primary();
            $table->string('server', 64)->index();
            $table->bigInteger('item_id')->index();
            $table->text('url')->nullable();
            $table->text('thumb_url')->nullable();
            $table->text('thumb_path')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->bigInteger('local_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_photos');
    }
};
