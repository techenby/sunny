<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('berries_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('frequency');
            $table->double('amount');
            $table->timestamp('billed_at');
            $table->timestamp('due_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('berries_subscriptions');
    }
};
