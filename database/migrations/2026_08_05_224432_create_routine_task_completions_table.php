<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('routine_task_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained('routine_tasks')->cascadeOnDelete();
            $table->foreignIdFor(User::class)->nullable()->constrained()->nullOnDelete();
            $table->date('period_started_on');
            $table->timestamp('completed_at');

            $table->unique(['task_id', 'period_started_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('routine_task_completions');
    }
};
