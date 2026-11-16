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
    public const PLACES = ['Kitchen pantry', 'Garage', 'Hall closet', 'Basement', 'Attic', 'Laundry room', 'Shed', "Kids' rooms", 'Office'];

    public const GROCERIES = ['Milk', 'Eggs', 'Bread', 'Butter', 'Bananas', 'Coffee'];

    public const BEDTIME = ['Bath', 'Pajamas', 'Brush teeth', 'Read a story'];

    public const MORNING = ['Make bed', 'Get dressed', 'Breakfast', 'Brush teeth', 'Pack bags'];

    public const STARTERS = ['groceries', 'bedtime', 'morning', 'recipe'];

    public const RECIPE_URL = 'https://safelydelish.com/apple-cider-donuts/';

    public function __construct(
        private readonly SunnyOutbox $outbox,
        private readonly SunnyAuth $auth,
        private readonly SunnyStore $store,
    ) {}

    /**
     * @param  list<string>  $places
     * @param  list<string>  $starters
     */
    public function handle(Onboarding $onboarding, Team $team, array $places, array $starters): void
    {
        $created = DB::transaction(function () use ($team, $places, $starters): array {
            $created = [];

            foreach ($places as $place) {
                $created[] = ['resource' => 'items', 'id' => $this->outbox->queue('items', $team->id, [
                    'name' => $place, 'type' => ItemType::Location->value, 'parent_id' => null, 'metadata' => null,
                ])];
            }

            if (in_array('groceries', $starters, true)) {
                $id = $this->outbox->queue('checklists', $team->id, ['name' => 'Groceries', 'type' => ChecklistType::Shopping->value]);
                foreach (self::GROCERIES as $name) {
                    $this->outbox->queue('checklist_items', $team->id, ['checklist_id' => $id, 'name' => $name]);
                }
                $created[] = ['resource' => 'checklists', 'id' => $id];
            }

            if (in_array('bedtime', $starters, true)) {
                $created[] = $this->createRoutine($team, 'Bedtime', TimeOfDay::Evening, RoutineFrequency::Daily, self::BEDTIME);
            }

            if (in_array('morning', $starters, true)) {
                $created[] = $this->createRoutine($team, 'Morning', TimeOfDay::Morning, RoutineFrequency::Weekly, self::MORNING, [1, 2, 3, 4, 5]);
            }

            return $created;
        });

        if (in_array('recipe', $starters, true) && ($id = $this->importRecipe($team)) !== null) {
            $created[] = ['resource' => 'recipes', 'id' => $id];
        }

        $onboarding->update(['set_up_at' => now(), 'starters' => $created]);
        app(SunnySyncCoordinator::class)->dispatch();
        $onboarding->report();
    }

    /**
     * @param  list<string>  $steps
     * @param  list<int>|null  $weekdays
     * @return array{resource: string, id: int}
     */
    private function createRoutine(Team $team, string $name, TimeOfDay $timeOfDay, RoutineFrequency $frequency, array $steps, ?array $weekdays = null): array
    {
        $id = $this->outbox->queue('routines', $team->id, [
            'name' => $name,
            'time_of_day' => $timeOfDay->value,
            'frequency' => $frequency->value,
            'weekdays' => $weekdays,
            'day_of_month' => null,
            'is_active' => true,
        ]);

        foreach ($steps as $step) {
            $this->outbox->queue('routine_steps', $team->id, ['routine_id' => $id, 'name' => $step]);
        }

        return ['resource' => 'routines', 'id' => $id];
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
