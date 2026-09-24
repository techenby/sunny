<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('personal_access_tokens')
            ->whereNull('expires_at')
            ->update(['expires_at' => now()->addDays(30)]);
    }

    public function down(): void
    {
        //
    }
};
