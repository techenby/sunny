<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RoutineTaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['routine_id', 'name', 'order'])]
class RoutineTask extends Model
{
    /** @use HasFactory<RoutineTaskFactory> */
    use HasFactory;

    /** @return BelongsTo<Routine, $this> */
    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }
}
