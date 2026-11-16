<?php

namespace App\Actions;

use App\Enums\ChecklistType;
use App\Enums\ItemType;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\Requests\ImportRecipeRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Models\Onboarding;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SetUpHome
{
    public const PLACES = ['Kitchen pantry', 'Garage', 'Hall closet', 'Basement', 'Attic', 'Shed'];

    public const GROCERIES = ['Milk', 'Eggs', 'Bread', 'Butter', 'Bananas', 'Coffee'];

    public const BEDTIME = ['Bath', 'Pajamas', 'Brush teeth', 'Read a story'];

    public const RECIPE_URL = 'https://safelydelish.com/apple-cider-donuts/';

    public function __construct(
        private readonly SunnyOutbox $outbox,
        private readonly SunnyAuth $auth,
        private readonly SunnyStore $store,
    ) {}

    /**
     * @param  list<string>  $places
     */
    public function handle(Onboarding $onboarding, Team $team, array $places, bool $groceries, bool $bedtime, bool $recipe): void
    {
        $starters = DB::transaction(function () use ($team, $places, $groceries, $bedtime): array {
            $starters = [];

            foreach ($places as $place) {
                $starters[] = ['resource' => 'items', 'id' => $this->outbox->queue('items', $team->id, [
                    'name' => $place, 'type' => ItemType::Location->value, 'parent_id' => null, 'metadata' => null,
                ])];
            }

            if ($groceries) {
                $id = $this->outbox->queue('checklists', $team->id, ['name' => 'Groceries', 'type' => ChecklistType::Shopping->value]);
                foreach (self::GROCERIES as $name) {
                    $this->outbox->queue('checklist_items', $team->id, ['checklist_id' => $id, 'name' => $name]);
                }
                $starters[] = ['resource' => 'checklists', 'id' => $id];
            }

            if ($bedtime) {
                $id = $this->outbox->queue('routines', $team->id, [
                    'name' => 'Bedtime',
                    'time_of_day' => TimeOfDay::Evening->value,
                    'frequency' => RoutineFrequency::Daily->value,
                    'weekdays' => null,
                    'day_of_month' => null,
                    'is_active' => true,
                ]);
                foreach (self::BEDTIME as $name) {
                    $this->outbox->queue('routine_steps', $team->id, ['routine_id' => $id, 'name' => $name]);
                }
                $starters[] = ['resource' => 'routines', 'id' => $id];
            }

            return $starters;
        });

        if ($recipe && ($id = $this->importRecipe($team)) !== null) {
            $starters[] = ['resource' => 'recipes', 'id' => $id];
        }

        $onboarding->update(['set_up_at' => now(), 'starters' => $starters]);
        app(SunnySyncCoordinator::class)->dispatch();
        $onboarding->report();
    }

    private function importRecipe(Team $team): ?int
    {
        if (! $team->slug) {
            return null;
        }

        try {
            $record = $this->auth->authenticatedConnector()
                ->send(new ImportRecipeRequest($team->slug, self::RECIPE_URL, (string) Str::uuid()))
                ->json('data');
        } catch (Throwable) {
            return null;
        }

        if (! is_array($record) || ! is_int($record['id'] ?? null) || ($record['team_id'] ?? null) !== $team->id) {
            return null;
        }

        $this->store->saveRecord('recipes', $record);

        return $record['id'];
    }
}
