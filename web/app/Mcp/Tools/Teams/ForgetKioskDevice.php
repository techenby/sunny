<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Teams;

use App\Models\KioskDevice;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description('Unpair a kiosk device from the current team. The device stops showing the team\'s dashboard and must be paired again to reconnect. Requires permission to update the team (owner or admin). Use the list-kiosk-devices tool to find device ids.')]
class ForgetKioskDevice extends Tool
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ], [
            'id.required' => 'You must provide the id of the kiosk device to forget. Use the list-kiosk-devices tool to find it.',
        ]);

        $team = $request->user()->currentTeam;

        $device = $team === null ? null : KioskDevice::query()
            ->whereBelongsTo($team)
            ->whereKey($validated['id'])
            ->first();

        if ($device === null) {
            return Response::error('Kiosk device not found.');
        }

        Gate::forUser($request->user())->authorize('update', $team);

        $device->delete();

        $name = $device->name ?? 'Unnamed device';

        return Response::text("Kiosk device \"{$name}\" (ID {$device->id}) forgotten.");
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the kiosk device to forget. Use the list-kiosk-devices tool to find device ids.')
                ->required(),
        ];
    }
}
