<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Routines;

use App\Mcp\Tools\Routines\Concerns\FormatsRoutines;
use App\Models\Routine;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description("List every routine on the current team, including paused ones, with its owner, time of day, schedule, and step count. Use the get-routine tool for a routine's steps, or the get-routine-board tool for what is due on a given day and whether it has been done.")]
class ListRoutines extends Tool
{
    use FormatsRoutines;

    public function handle(Request $request): ResponseFactory
    {
        Gate::forUser($request->user())->authorize('viewAny', Routine::class);

        $routines = $request->user()->currentTeam->routines()
            ->with('user')
            ->withCount('steps')
            ->orderBy('name')
            ->get();

        return Response::structured([
            'count' => $routines->count(),
            'routines' => $routines->map(fn (Routine $routine): array => $this->routinePayload($routine))->all(),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'count' => $schema->integer()->required(),
            'routines' => $schema->array()->items($schema->object($this->routineSchema($schema)))->required(),
        ];
    }
}
