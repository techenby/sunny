<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Teams;

use App\Mcp\Tools\Teams\Concerns\FormatsTeams;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Switch your current team. Every other Sunny tool acts on the current team, so call this before working with another household\'s data. Warning: the current team is shared with the Sunny web app, so this also changes the team you see there. Use the list-teams tool to find team ids.')]
class SwitchTeam extends Tool
{
    use FormatsTeams;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'team_id' => ['required', 'integer'],
        ], [
            'team_id.required' => 'You must provide the team_id of the team to switch to. Use the list-teams tool to find it.',
        ]);

        $user = $request->user();

        $team = $user->teams()->whereKey($validated['team_id'])->first();

        if ($team === null || ! $user->switchTeam($team)) {
            return Response::error('Team not found.');
        }

        return Response::structured($this->teamData($team, $user->teamRole($team), true));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'team_id' => $schema->integer()
                ->description('The id of the team to make current. Use the list-teams tool to find team ids.')
                ->required(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->teamSchema($schema);
    }
}
