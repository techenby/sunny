<?php

declare(strict_types=1);

namespace App\Mcp\Tools\MealPlanning;

use App\Actions\MealPlanning\ResolveMealPlan;
use App\Mcp\Tools\MealPlanning\Concerns\InteractsWithMeals;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description("Get the meal plan for a run of days: every meal slot (such as Breakfast, Lunch, Dinner) on each day with its meals. Use this to answer \"what's for dinner?\". Meals come from the weekly template unless that slot was changed on that date (overridden: true). Past days show what was saved when they ended. Defaults to 7 days from today in the team's timezone.")]
class GetMealPlan extends Tool
{
    use InteractsWithMeals;

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:31'],
        ], [
            'from.date_format' => 'The from date must be in Y-m-d format, for example "2026-07-08".',
            'days.max' => 'The meal plan can be read at most 31 days at a time.',
        ]);

        $team = $this->team($request);
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from'], $team->timezone) : $team->today();
        $through = $from->addDays((int) ($validated['days'] ?? 7) - 1);

        return Response::structured([
            'timezone' => $team->timezone,
            'days' => (new ResolveMealPlan)->handle($team, $from, $through)
                ->map(fn (array $day): array => [
                    'date' => $day['date']->toDateString(),
                    'weekday' => $day['date']->format('l'),
                    'saved' => $day['saved'],
                    'slots' => $day['slots']
                        ->map(fn (array $cell): array => [
                            'meal_slot_id' => $cell['slot']->id,
                            'slot_name' => $cell['slot']->name,
                            'overridden' => $cell['overridden'],
                            'items' => $this->formatMealItems($cell['items']),
                        ])
                        ->all(),
                ])
                ->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()
                ->description('The first day, as Y-m-d. Defaults to today in the team\'s timezone.'),
            'days' => $schema->integer()->min(1)->max(31)
                ->description('How many days to include, from 1 to 31. Defaults to 7.'),
        ];
    }

    /** @return array<string, Type> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'timezone' => $schema->string()->required(),
            'days' => $schema->array()->items($schema->object([
                'date' => $schema->string()->required(),
                'weekday' => $schema->string()->required(),
                'saved' => $schema->boolean()->required()
                    ->description('True once the day has ended and its meals are saved as history.'),
                'slots' => $schema->array()->items($schema->object([
                    'meal_slot_id' => $schema->integer()->required(),
                    'slot_name' => $schema->string()->required(),
                    'overridden' => $schema->boolean()->required()
                        ->description('True when this slot was changed on this date instead of following the weekly template.'),
                    'items' => $schema->array()->items($this->mealItemOutputSchema($schema))->required(),
                ]))->required(),
            ]))->required(),
        ];
    }
}
