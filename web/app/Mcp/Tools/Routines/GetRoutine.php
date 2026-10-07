<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Routines;

use App\Mcp\Tools\Routines\Concerns\FormatsRoutines;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Fetch a single routine from the current team by id, including its owner, schedule, and its steps in order.')]
class GetRoutine extends Tool
{
    use FormatsRoutines;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $routine = $request->user()->currentTeam->routines()->with(['user', 'steps'])->find($validated['id']);

        if (! $routine) {
            return Response::error('Routine not found.');
        }

        Gate::forUser($request->user())->authorize('view', $routine);

        return Response::structured($this->routineWithStepsPayload($routine));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the routine to fetch. Use the list-routines tool to find ids.')
                ->required(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->routineWithStepsSchema($schema);
    }
}
