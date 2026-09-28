<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sunny_accounts', function (Blueprint $table): void {
            $table->string('server', 64)->primary();
            $table->unsignedBigInteger('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sunny_accounts');
    }
};
