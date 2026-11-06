<?php

declare(strict_types=1);

namespace App\Mcp\Tools\MealPlanning;

use App\Mcp\Tools\MealPlanning\Concerns\InteractsWithMeals;
use App\Models\MealSlot;
use App\Models\MealTemplateItem;
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
#[Description("Get the team's meal slots and weekly meal template: the meals that repeat on each weekday, such as tacos every Tuesday dinner. Weekdays are integers, 0 = Sunday through 6 = Saturday, listed in the order the team's week runs.")]
class GetMealTemplate extends Tool
{
    use InteractsWithMeals;

    public function handle(Request $request): Response|ResponseFactory
    {
        $team = $this->team($request);
        $slots = $team->mealSlots()->get();
        $items = $team->mealTemplateItems()
            ->with('recipe')
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (MealTemplateItem $item): string => $item->weekday . ':' . $item->meal_slot_id);

        $start = CarbonImmutable::now()->startOfWeek((int) $team->week_start);

        return Response::structured([
            'slots' => $slots
                ->map(fn (MealSlot $slot): array => ['id' => $slot->id, 'name' => $slot->name])
                ->all(),
            'weekdays' => collect(range(0, 6))
                ->map(fn (int $offset): CarbonImmutable => $start->addDays($offset))
                ->map(fn (CarbonImmutable $day): array => [
                    'weekday' => $day->dayOfWeek,
                    'name' => $day->format('l'),
                    'slots' => $slots
                        ->map(fn (MealSlot $slot): array => [
                            'meal_slot_id' => $slot->id,
                            'slot_name' => $slot->name,
                            'items' => $this->formatMealItems($items->get($day->dayOfWeek . ':' . $slot->id) ?? collect()),
                        ])
                        ->all(),
                ])
                ->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    /** @return array<string, Type> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'slots' => $schema->array()->items($schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->required(),
            ]))->required()->description('The team\'s meal slots, in order.'),
            'weekdays' => $schema->array()->items($schema->object([
                'weekday' => $schema->integer()->min(0)->max(6)->required(),
                'name' => $schema->string()->required(),
                'slots' => $schema->array()->items($schema->object([
                    'meal_slot_id' => $schema->integer()->required(),
                    'slot_name' => $schema->string()->required(),
                    'items' => $schema->array()->items($this->mealItemOutputSchema($schema))->required(),
                ]))->required(),
            ]))->required(),
        ];
    }
}
