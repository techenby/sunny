<?php

namespace App\Http\Integrations\Sunny;

use App\Models\Team;
use Illuminate\Support\Facades\DB;

class SunnyTeam
{
    public function current(): ?Team
    {
        $team = Team::query()->where('server', SunnyStore::server())->orderByDesc('is_active')->orderBy('id')->first();
        if ($team && ! $team->is_active) {
            $this->select($team->id);
        }

        return $team;
    }

    public function select(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $team = Team::query()->where('server', SunnyStore::server())->findOrFail($id);
            Team::query()->where('server', SunnyStore::server())->update(['is_active' => false]);
            $team->update(['is_active' => true]);
        });
    }

    /** @return array<int, string> */
    public function choices(): array
    {
        $teams = Team::query()->where('server', SunnyStore::server())->orderBy('name')->orderBy('id')->get();
        $duplicates = $teams->pluck('name')->duplicates()->all();

        return $teams->mapWithKeys(fn (Team $team): array => [$team->id => in_array($team->name, $duplicates, true)
            ? $team->name.' (#'.$team->id.')' : $team->name])->all();
    }
}
