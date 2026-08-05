<?php

declare(strict_types=1);

namespace App\Actions\Kiosk;

use App\Models\Routine;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateRoutine
{
    /**
     * @param  array{name: string, cadence: string, reset_weekday: ?int, tasks: array<int, array{key: string, id: ?int, name: string}>}  $data
     */
    public function handle(Team $team, array $data): Routine
    {
        return DB::transaction(function () use ($team, $data): Routine {
            $routine = $team->routines()->create(Arr::except($data, 'tasks'));

            $routine->tasks()->createMany(
                collect($data['tasks'])
                    ->values()
                    ->map(fn (array $task, int $index): array => [
                        'name' => $task['name'],
                        'order' => $index,
                    ])
                    ->all()
            );

            return $routine->load('tasks');
        });
    }
}
