<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lego_bins', function (Blueprint $table) {
            $table->string('baseplate')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('lego_bins', function (Blueprint $table) {
            $table->dropColumn('baseplate');
        });
    }
};
