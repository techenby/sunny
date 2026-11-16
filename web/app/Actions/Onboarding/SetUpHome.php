<?php

declare(strict_types=1);

namespace App\Actions\Onboarding;

use App\Actions\Recipes\CreateRecipe;
use App\Actions\Recipes\ImportRecipeFromUrl;
use App\Enums\ChecklistType;
use App\Enums\ItemType;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonInterface;
use Throwable;

class SetUpHome
{
    public const string SAMPLE_RECIPE_URL = 'https://safelydelish.com/apple-cider-donuts/';

    public const array PLACES = ['Kitchen pantry', 'Garage', 'Hall closet', 'Basement', 'Attic', 'Laundry room', 'Shed', "Kids' rooms", 'Office'];

    public const array GROCERIES = ['Milk', 'Eggs', 'Bread', 'Butter', 'Bananas', 'Coffee'];

    public const array BEDTIME = ['Bath', 'Pajamas', 'Brush teeth', 'Read a story'];

    public const array MORNING = ['Make bed', 'Get dressed', 'Breakfast', 'Brush teeth', 'Pack bags'];

    public const array STARTERS = ['groceries', 'bedtime', 'morning', 'recipe'];

    public function __construct(
        private CompleteOnboarding $completeOnboarding,
        private ImportRecipeFromUrl $importRecipe,
        private CreateRecipe $createRecipe,
    ) {}

    /**
     * @param  list<string>  $places
     * @param  list<string>  $starters
     */
    public function handle(User $user, array $places, array $starters): bool
    {
        $team = null;

        $didSetUp = $this->completeOnboarding->handle($user, function (Team $currentTeam) use (&$team, $places, $starters): void {
            $team = $currentTeam;

            collect($places)
                ->map(fn (string $place): string => trim($place))
                ->filter()
                ->unique(fn (string $place): string => mb_strtolower($place))
                ->each(fn (string $place) => $team->items()->create(['name' => $place, 'type' => ItemType::Location]));

            if (in_array('groceries', $starters, true)) {
                $this->createGroceries($team);
            }

            if (in_array('bedtime', $starters, true)) {
                $this->createRoutine($team, __('Bedtime'), TimeOfDay::Evening, RoutineFrequency::Daily, self::BEDTIME);
            }

            if (in_array('morning', $starters, true)) {
                $this->createRoutine($team, __('Morning'), TimeOfDay::Morning, RoutineFrequency::Weekly, self::MORNING, [
                    CarbonInterface::MONDAY,
                    CarbonInterface::TUESDAY,
                    CarbonInterface::WEDNESDAY,
                    CarbonInterface::THURSDAY,
                    CarbonInterface::FRIDAY,
                ]);
            }
        });

        if ($didSetUp && $team && in_array('recipe', $starters, true)) {
            $this->importSampleRecipe($team);
        }

        return $didSetUp;
    }

    private function createGroceries(Team $team): void
    {
        $list = $team->checklists()->create([
            'type' => ChecklistType::Shopping,
            'name' => __('Groceries'),
        ]);

        foreach (self::GROCERIES as $position => $name) {
            $list->items()->create(['name' => __($name), 'position' => $position]);
        }
    }

    /**
     * @param  list<string>  $steps
     * @param  list<int>|null  $weekdays
     */
    private function createRoutine(Team $team, string $name, TimeOfDay $timeOfDay, RoutineFrequency $frequency, array $steps, ?array $weekdays = null): void
    {
        $routine = $team->routines()->create([
            'name' => $name,
            'time_of_day' => $timeOfDay,
            'frequency' => $frequency,
            'weekdays' => $weekdays,
            'starts_on' => $team->today()->toDateString(),
            'is_active' => true,
        ]);

        foreach ($steps as $position => $step) {
            $routine->steps()->create(['name' => __($step), 'position' => $position]);
        }
    }

    private function importSampleRecipe(Team $team): void
    {
        try {
            $data = $this->importRecipe->handle(self::SAMPLE_RECIPE_URL);

            $this->createRecipe->handle($team, array_filter($data, fn (mixed $value): bool => filled($value)));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
