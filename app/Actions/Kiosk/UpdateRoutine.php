<?php

declare(strict_types=1);

namespace App\Actions\Kiosk;

use App\Models\Routine;
use App\Models\RoutineTask;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateRoutine
{
    /**
     * @param  array{name: string, cadence: string, reset_weekday: ?int, tasks: array<int, array{key: string, id: ?int, name: string}>}  $data
     */
    public function handle(Routine $routine, array $data): Routine
    {
        return DB::transaction(function () use ($routine, $data): Routine {
            $routine->update(Arr::except($data, 'tasks'));

            $existingTasks = $routine->tasks()->get()->keyBy('id');
            $retainedTaskIds = [];

            foreach (array_values($data['tasks']) as $index => $taskData) {
                $task = $taskData['id'] ? $existingTasks->get($taskData['id']) : null;

                if ($task instanceof RoutineTask) {
                    $task->update([
                        'name' => $taskData['name'],
                        'order' => $index,
                    ]);
                } else {
                    $task = $routine->tasks()->create([
                        'name' => $taskData['name'],
                        'order' => $index,
                    ]);
                }

                $retainedTaskIds[] = $task->id;
            }

            $routine->tasks()->whereNotIn('id', $retainedTaskIds)->delete();

            return $routine->load('tasks');
        });
    }
}
