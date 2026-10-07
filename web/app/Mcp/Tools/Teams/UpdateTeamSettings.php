<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Teams;

use App\Enums\Appearance;
use App\Mcp\Tools\Teams\Concerns\FormatsTeams;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Enum;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Update the current team\'s household and kiosk settings: timezone, first day of the week, kiosk appearance, screen rotation, or address. Only the provided fields change. Address fields are merged into the existing address, but the result must have every field (address, city, state, zip, lat, long). Requires permission to update the team (owner or admin). Returns the updated settings.')]
class UpdateTeamSettings extends Tool
{
    use FormatsTeams;

    public function handle(Request $request): Response|ResponseFactory
    {
        $user = $request->user();
        $team = $user->currentTeam;

        if ($team === null) {
            return Response::error('Team not found.');
        }

        Gate::forUser($user)->authorize('update', $team);

        $validated = $request->validate([
            'timezone' => ['sometimes', 'string', 'timezone:all'],
            'week_start' => ['sometimes', 'integer', 'between:0,6'],
            'appearance' => ['sometimes', new Enum(Appearance::class)],
            'rotation' => ['sometimes', 'integer', 'in:0,90,180,270'],
            'address' => ['sometimes', 'array'],
            'address.address' => ['sometimes', 'required', 'string', 'max:255'],
            'address.city' => ['sometimes', 'required', 'string', 'max:255'],
            'address.state' => ['sometimes', 'required', 'string', 'max:255'],
            'address.zip' => ['sometimes', 'required', 'string', 'max:255'],
            'address.lat' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'address.long' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
        ], [
            'timezone.timezone' => 'The timezone must be a valid IANA timezone, such as America/Chicago.',
            'week_start.between' => 'The week_start must be between 0 (Sunday) and 6 (Saturday).',
            'appearance.Illuminate\Validation\Rules\Enum' => 'The appearance must be one of: ' . implode(', ', array_column(Appearance::cases(), 'value')) . '.',
            'rotation.in' => 'The rotation must be one of: 0, 90, 180, 270.',
        ]);

        $data = Arr::only($validated, ['timezone', 'week_start', 'appearance', 'rotation']);

        if (array_key_exists('address', $validated)) {
            $address = [
                ...($team->address ?? []),
                ...Arr::map(Arr::only($validated['address'], $this->addressKeys), fn (mixed $value): string => (string) $value),
            ];

            $missing = array_values(array_filter(
                $this->addressKeys,
                fn (string $key): bool => blank($address[$key] ?? null),
            ));

            if ($missing !== []) {
                return Response::error('The address is incomplete. Also provide: ' . implode(', ', $missing) . '.');
            }

            $data['address'] = Arr::only($address, $this->addressKeys);
        }

        if ($data === []) {
            return Response::error('Provide at least one field to update: timezone, week_start, appearance, rotation, or address.');
        }

        $team->update($data);

        return Response::structured($this->teamSettings($team->refresh()));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'timezone' => $schema->string()
                ->description('IANA timezone, e.g. America/Chicago or Europe/London.'),
            'week_start' => $schema->integer()
                ->min(0)
                ->max(6)
                ->description('First day of the week, where 0 is Sunday, 1 is Monday, and 6 is Saturday.'),
            'appearance' => $schema->string()
                ->enum(Appearance::class)
                ->description('Kiosk color scheme: "light", "dark", or "system".'),
            'rotation' => $schema->integer()
                ->enum([0, 90, 180, 270])
                ->description('Kiosk screen rotation in degrees: 0, 90, 180, or 270.'),
            'address' => $schema->object([
                'address' => $schema->string()->description('Street address.'),
                'city' => $schema->string(),
                'state' => $schema->string(),
                'zip' => $schema->string(),
                'lat' => $schema->number()->description('Latitude, between -90 and 90.'),
                'long' => $schema->number()->description('Longitude, between -180 and 180.'),
            ])->description('The household address, used for the kiosk weather tile. Provided fields are merged into the existing address; if the team has no address yet, provide all six fields.'),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->teamSettingsSchema($schema);
    }
}
