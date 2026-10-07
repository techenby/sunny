<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Teams\Concerns;

use App\Enums\Appearance;
use App\Enums\TeamRole;
use App\Models\Team;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Date;

trait FormatsTeams
{
    /** @var list<string> */
    protected array $addressKeys = ['address', 'city', 'state', 'zip', 'lat', 'long'];

    /** @return array<string, mixed> */
    protected function teamData(Team $team, ?TeamRole $role, bool $isCurrent): array
    {
        return [
            'id' => $team->id,
            'name' => $team->name,
            'slug' => $team->slug,
            'is_personal' => $team->is_personal,
            'role' => $role?->value,
            'is_current' => $isCurrent,
        ];
    }

    /** @return array<string, JsonSchema> */
    protected function teamSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'slug' => $schema->string()->required(),
            'is_personal' => $schema->boolean()->required(),
            'role' => $schema->string()->enum(TeamRole::class)->nullable()->required()
                ->description('Your role on this team.'),
            'is_current' => $schema->boolean()->required()
                ->description('Whether this is your current team, which every other Sunny tool acts on.'),
        ];
    }

    /** @return array<string, mixed> */
    protected function teamSettings(Team $team): array
    {
        $address = $team->address === null ? null : collect($this->addressKeys)
            ->mapWithKeys(fn (string $key): array => [$key => isset($team->address[$key]) ? (string) $team->address[$key] : null])
            ->all();

        return [
            'id' => $team->id,
            'name' => $team->name,
            'timezone' => $team->timezone,
            'week_start' => $team->week_start,
            'week_start_day' => Date::getDays()[$team->week_start],
            'appearance' => $team->appearance->value,
            'rotation' => $team->rotation,
            'address' => $address,
        ];
    }

    /** @return array<string, JsonSchema> */
    protected function teamSettingsSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'timezone' => $schema->string()->required()
                ->description('IANA timezone, e.g. America/Chicago.'),
            'week_start' => $schema->integer()->min(0)->max(6)->required()
                ->description('First day of the week, where 0 is Sunday and 6 is Saturday.'),
            'week_start_day' => $schema->string()->required()
                ->description('The name of the first day of the week, e.g. Sunday.'),
            'appearance' => $schema->string()->enum(Appearance::class)->required()
                ->description('Kiosk color scheme.'),
            'rotation' => $schema->integer()->enum([0, 90, 180, 270])->required()
                ->description('Kiosk screen rotation in degrees.'),
            'address' => $schema->object([
                'address' => $schema->string()->nullable()->required(),
                'city' => $schema->string()->nullable()->required(),
                'state' => $schema->string()->nullable()->required(),
                'zip' => $schema->string()->nullable()->required(),
                'lat' => $schema->string()->nullable()->required(),
                'long' => $schema->string()->nullable()->required(),
            ])->nullable()->required()
                ->description('The household address, used for the kiosk weather tile. Null when not set.'),
        ];
    }
}
