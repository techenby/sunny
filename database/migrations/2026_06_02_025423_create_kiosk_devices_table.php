<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_devices', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('pairing_code')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'paired_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_devices');
    }
};
