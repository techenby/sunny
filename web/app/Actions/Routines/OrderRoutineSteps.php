<?php

declare(strict_types=1);

namespace App\Actions\Routines;

use App\Models\Routine;
use App\Models\RoutineStep;
use Illuminate\Support\Facades\DB;

class OrderRoutineSteps
{
    /** @param array<int, int> $ids */
    public function handle(Routine $routine, array $ids): void
    {
        DB::transaction(function () use ($routine, $ids): void {
            $steps = $routine->steps()->lockForUpdate()->get();
            $order = array_flip(array_values(array_unique($ids)));

            $sorted = $steps->sortBy(fn (RoutineStep $step, int $index) => [
                $order[$step->id] ?? count($order),
                $index,
            ])->values();

            foreach ($sorted as $index => $step) {
                if ((int) $step->position !== $index + 1) {
                    $step->update(['position' => $index + 1]);
                }
            }
        });
    }
}
