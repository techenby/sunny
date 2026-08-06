<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RoutineTaskCompletionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'user_id', 'period_started_on', 'completed_at'])]
class RoutineTaskCompletion extends Model
{
    /** @use HasFactory<RoutineTaskCompletionFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @return BelongsTo<RoutineTask, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(RoutineTask::class, 'task_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_started_on' => 'immutable_date',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
