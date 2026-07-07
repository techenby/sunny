<?php

use App\Enums\Appearance;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('appearance')->default(Appearance::Dark->value)->after('address');
            $table->string('layout')->default('landscape')->after('appearance');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumns(['appearance', 'layout']);
        });
    }
};
