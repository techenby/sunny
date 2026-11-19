<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sunny_accounts', function (Blueprint $table): void {
            $table->timestamp('email_verification_grace_ends_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sunny_accounts', function (Blueprint $table): void {
            $table->dropColumn('email_verification_grace_ends_at');
        });
    }
};
