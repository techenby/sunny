<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Teams;

use App\Enums\TeamRole;
use App\Mcp\Tools\Teams\Concerns\FormatsTeams;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the teams (households) you belong to, with your role on each and which one is your current team. Every other Sunny tool reads and writes data on the current team only; use the switch-team tool to change it.')]
class ListTeams extends Tool
{
    use FormatsTeams;

    public function handle(Request $request): ResponseFactory
    {
        $user = $request->user();

        $teams = $user->teams()
            ->orderByRaw('LOWER(teams.name)')
            ->get();

        return Response::structured([
            'count' => $teams->count(),
            'current_team_id' => $user->current_team_id,
            'teams' => $teams->map(fn (Team $team): array => $this->teamData(
                $team,
                TeamRole::tryFrom($team->pivot->role),
                $user->isCurrentTeam($team),
            ))->all(),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'count' => $schema->integer()->required(),
            'current_team_id' => $schema->integer()->nullable()->required(),
            'teams' => $schema->array()->items($schema->object($this->teamSchema($schema)))->required(),
        ];
    }
}
