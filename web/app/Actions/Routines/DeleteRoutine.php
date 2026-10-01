<?php

declare(strict_types=1);

namespace App\Actions\Routines;

use App\Models\Routine;

class DeleteRoutine
{
    public function handle(Routine $routine): void
    {
        $routine->delete();
    }
}
