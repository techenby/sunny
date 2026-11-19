<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sunny_accounts', function (Blueprint $table): void {
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sunny_accounts', function (Blueprint $table): void {
            $table->dropColumn(['name', 'email', 'email_verified_at']);
        });
    }
};
