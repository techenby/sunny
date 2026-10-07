<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Teams;

use App\Mcp\Tools\Teams\Concerns\FormatsTeams;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the current team\'s household and kiosk settings: timezone, first day of the week, kiosk appearance and screen rotation, and the household address used for weather. Use the switch-team tool to read another team\'s settings.')]
class GetTeamSettings extends Tool
{
    use FormatsTeams;

    public function handle(Request $request): Response|ResponseFactory
    {
        $team = $request->user()->currentTeam;

        if ($team === null) {
            return Response::error('Team not found.');
        }

        Gate::forUser($request->user())->authorize('view', $team);

        return Response::structured($this->teamSettings($team));
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->teamSettingsSchema($schema);
    }
}
