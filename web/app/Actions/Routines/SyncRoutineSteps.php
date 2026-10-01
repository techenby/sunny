<?php

declare(strict_types=1);

namespace App\Actions\Routines;

use App\Models\Routine;

class SyncRoutineSteps
{
    /**
     * @param  array<int, array{id?: int|null, name: string}>  $steps
     */
    public function handle(Routine $routine, array $steps): void
    {
        $existing = $routine->steps()->get()->keyBy('id');

        $kept = [];

        ksort($steps);

        foreach (array_values($steps) as $index => $entry) {
            $attributes = ['name' => $entry['name'], 'position' => $index + 1];

            if (isset($entry['id']) && $existing->has($entry['id'])) {
                $existing->get($entry['id'])->update($attributes);
                $kept[] = $entry['id'];
            } else {
                $routine->steps()->create($attributes);
            }
        }

        $existing->except($kept)->each->delete();
    }
}
