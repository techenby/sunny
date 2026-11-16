<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Enums\ChecklistType;
use App\Enums\ItemType;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

class CompleteOnboarding
{
    public const string LIST_NAME = 'Get started with SunnyHome';

    /** @param  (Closure(Team): void)|null  $setUp */
    public function handle(User $user, ?Closure $setUp = null): bool
    {
        return DB::transaction(function () use ($user, $setUp): bool {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (! $user->needsOnboarding()) {
                return false;
            }

            $team = $user->currentTeam;

            if ($setUp) {
                $setUp($team);
            }

            $user->update(['onboarded_at' => now()]);

            $this->createGettingStartedList($user, $team);

            return true;
        });
    }

    private function createGettingStartedList(User $user, Team $team): void
    {
        $list = $team->checklists()->create([
            'user_id' => $user->id,
            'type' => ChecklistType::Todo,
            'name' => __(self::LIST_NAME),
        ]);

        $place = $team->items()
            ->where('type', ItemType::Location)
            ->whereNull('parent_id')
            ->oldest('id')
            ->first();

        $items = [
            ['name' => __('Set up your home'), 'url' => null],
            [
                'name' => __('Put something away'),
                'url' => route('inventory.index', array_filter(['current_team' => $team->slug, 'parentId' => $place?->id])),
            ],
            ['name' => __('Save a recipe'), 'url' => route('recipes.create', ['current_team' => $team->slug])],
            ['name' => __('Invite your family'), 'url' => route('teams.edit', $team->slug)],
        ];

        foreach ($items as $position => $item) {
            $created = $list->items()->create([...$item, 'position' => $position]);

            if ($position === 0) {
                $created->complete($user);
            }
        }
    }
}
