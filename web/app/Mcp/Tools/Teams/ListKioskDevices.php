<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Teams;

use App\Models\KioskDevice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the kiosk devices (wall displays) paired with the current team, most recently seen first. Use the forget-kiosk-device tool to unpair one.')]
class ListKioskDevices extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $team = $request->user()->currentTeam;

        if ($team === null) {
            return Response::error('Team not found.');
        }

        Gate::forUser($request->user())->authorize('view', $team);

        $devices = KioskDevice::query()
            ->whereBelongsTo($team)
            ->paired()
            ->orderByDesc('last_seen_at')
            ->get();

        return Response::structured([
            'count' => $devices->count(),
            'devices' => $devices->map(fn (KioskDevice $device): array => [
                'id' => $device->id,
                'name' => $device->name,
                'user_agent' => $device->user_agent,
                'paired_at' => $device->paired_at?->toIso8601String(),
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            ])->all(),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'count' => $schema->integer()->required(),
            'devices' => $schema->array()->items($schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->nullable()->required(),
                'user_agent' => $schema->string()->nullable()->required(),
                'paired_at' => $schema->string()->nullable()->required(),
                'last_seen_at' => $schema->string()->nullable()->required()
                    ->description('When the device last checked in, or null if it never has.'),
            ]))->required(),
        ];
    }
}
