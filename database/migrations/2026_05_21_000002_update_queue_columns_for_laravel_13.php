<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempts')->change();
        });

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->string('connection')->change();
            $table->string('queue')->change();
        });

        if (! Schema::hasIndex('failed_jobs', ['connection', 'queue', 'failed_at'])) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $table->index(['connection', 'queue', 'failed_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('failed_jobs', ['connection', 'queue', 'failed_at'])) {
            Schema::table('failed_jobs', function (Blueprint $table) {
                $table->dropIndex(['connection', 'queue', 'failed_at']);
            });
        }

        Schema::table('failed_jobs', function (Blueprint $table) {
            $table->text('connection')->change();
            $table->text('queue')->change();
        });

        Schema::table('jobs', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->change();
        });
    }
};
