<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Teams;

use App\Mcp\Tools\Teams\Concerns\FormatsTeams;
use App\Models\Team;
use App\Rules\TeamName;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Rename the current team. The team\'s slug is regenerated from the new name. Requires permission to update the team (owner or admin). Use the switch-team tool first to rename a different team.')]
class UpdateTeam extends Tool
{
    use FormatsTeams;

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        $currentTeam = $user->currentTeam;

        if ($currentTeam === null) {
            return Response::error('Team not found.');
        }

        Gate::forUser($user)->authorize('update', $currentTeam);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', new TeamName],
        ], [
            'name.required' => 'You must provide a new name for the team.',
        ]);

        $team = DB::transaction(function () use ($currentTeam, $validated) {
            $team = Team::whereKey($currentTeam->id)->lockForUpdate()->firstOrFail();

            $team->update(['name' => $validated['name']]);

            return $team;
        });

        return Response::structured($this->teamData($team, $user->teamRole($team), true));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->max(255)
                ->description('The new name for the current team. Some names are reserved, such as "admin" or "settings".')
                ->required(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->teamSchema($schema);
    }
}
