<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description("Plan the family's meals for the coming days around the calendar, using the team's recipes, then build a shopping list for the ingredients.")]
class PlanMeals extends Prompt
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:14'],
            'preferences' => ['nullable', 'string', 'max:1000'],
        ], [
            'days.integer' => 'The number of days must be a whole number between 1 and 14.',
            'days.min' => 'The number of days must be a whole number between 1 and 14.',
            'days.max' => 'The number of days must be a whole number between 1 and 14.',
        ]);

        $team = $request->user()->currentTeam;
        $days = (int) ($validated['days'] ?? 7);
        $from = $team->today();
        $through = $from->addDays($days - 1);

        $lines = [
            "Help me plan dinners for the {$team->name} household for {$days} day(s), from {$from->format('l, F j')} through {$through->format('l, F j, Y')} ({$team->timezone}).",
            '',
            '1. Call get-calendar-events with from "' . $from->toDateString() . "\" and days {$days} to see which evenings are busy. Busy nights need quick meals or leftovers.",
            '2. Call search-recipes to see what recipes we have (search by tag or ingredient as needed), and get-recipe for the ones you are considering.',
            '3. Propose a plan: one dinner per day, with a short reason when the calendar shaped the choice. Prefer our own recipes; if you suggest something new, say so. Wait for me to confirm or adjust the plan.',
            '4. Once I confirm, combine the ingredients into one shopping list, merging duplicates. Call list-checklists with type "shopping": if there is a shopping list, add the items to it with add-checklist-items; otherwise create one with create-checklist. Skip pantry staples like salt, pepper, and oil unless a recipe needs a lot of them.',
        ];

        if (filled($validated['preferences'] ?? null)) {
            array_splice($lines, 1, 0, ['', "Keep these preferences in mind: {$validated['preferences']}"]);
        }

        return Response::text(implode("\n", $lines));
    }

    /** @return array<int, Argument> */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'days',
                description: 'How many days to plan, starting today (1 to 14). Defaults to 7.',
            ),
            new Argument(
                name: 'preferences',
                description: 'Anything to keep in mind, e.g. "vegetarian on Mondays" or "use up the chicken".',
            ),
        ];
    }
}
