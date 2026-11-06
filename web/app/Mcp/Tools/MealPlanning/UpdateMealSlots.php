<?php

declare(strict_types=1);

namespace App\Mcp\Tools\MealPlanning;

use App\Actions\MealPlanning\RemoveMealSlot;
use App\Mcp\Tools\MealPlanning\Concerns\InteractsWithMeals;
use App\Models\MealSlot;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[IsDestructive]
#[Description("Replace the team's meal slots, such as Breakfast, Lunch, and Dinner, with a new list in order. Keep a slot by passing its id (and rename it by changing its name); add a slot by leaving out the id. Any current slot left out is removed: it is cleared from the weekly template and upcoming days, while days that have ended keep what was planned. Confirm removals with the user first. Use get-meal-template to see the current slots.")]
class UpdateMealSlots extends Tool
{
    use InteractsWithMeals;

    public function handle(Request $request, RemoveMealSlot $removeMealSlot): Response|ResponseFactory
    {
        $validated = $request->validate([
            'slots' => ['required', 'array', 'min:1', 'max:10'],
            'slots.*.id' => ['nullable', 'integer', 'distinct'],
            'slots.*.name' => ['required', 'string', 'max:255'],
        ], [
            'slots.required' => 'Provide at least one meal slot.',
            'slots.min' => 'Provide at least one meal slot.',
            'slots.*.id.distinct' => 'Each slot id may only be listed once.',
            'slots.*.name.required' => 'Each slot needs a name.',
        ]);

        $team = $this->team($request);
        $existing = $team->mealSlots()->get()->keyBy('id');
        $keptIds = array_values(array_filter(array_map(fn (array $slot): ?int => isset($slot['id']) ? (int) $slot['id'] : null, $validated['slots'])));

        if (array_diff($keptIds, $existing->keys()->all()) !== []) {
            throw ValidationException::withMessages([
                'slots' => sprintf('Slot ids must be the team\'s current meal slot ids: [%s].', $existing->keys()->implode(', ')),
            ]);
        }

        DB::transaction(function () use ($validated, $existing, $keptIds, $team, $removeMealSlot): void {
            $existing->except($keptIds)->each(fn (MealSlot $slot) => $removeMealSlot->handle($slot));

            foreach ($validated['slots'] as $position => $slot) {
                $attributes = ['name' => trim($slot['name']), 'position' => $position + 1];

                isset($slot['id'])
                    ? $existing[(int) $slot['id']]->update($attributes)
                    : $team->mealSlots()->create($attributes);
            }
        });

        return Response::structured([
            'slots' => $team->mealSlots()->get()
                ->map(fn (MealSlot $slot): array => ['id' => $slot->id, 'name' => $slot->name])
                ->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'slots' => $schema->array()
                ->items($schema->object([
                    'id' => $schema->integer()->description('The id of an existing slot to keep. Leave out to add a new slot.'),
                    'name' => $schema->string()->max(255)->required(),
                ]))
                ->min(1)
                ->max(10)
                ->description('Every meal slot the team should have, in order, e.g. [{"id": 1, "name": "Breakfast"}, {"name": "Snack"}].')
                ->required(),
        ];
    }

    /** @return array<string, Type> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'slots' => $schema->array()->items($schema->object([
                'id' => $schema->integer()->required(),
                'name' => $schema->string()->required(),
            ]))->required()->description('The team\'s meal slots, in order.'),
        ];
    }
}
