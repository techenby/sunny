<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use Illuminate\Support\Facades\Auth;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Completions\CompletionResponse;
use Laravel\Mcp\Server\Contracts\Completable;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Description('Find where something is stored in the home inventory, e.g. "Where are the camping lanterns?".')]
class FindItem extends Prompt implements Completable
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'item' => ['required', 'string', 'max:255'],
        ], [
            'item.required' => 'Tell me what you are looking for.',
        ]);

        return Response::text(implode("\n", [
            "Where is \"{$validated['item']}\" in our home inventory?",
            '',
            '1. Call search-items with the item as the query. If nothing matches, try a shorter name, a synonym, or the singular form.',
            '2. Call get-item on the best match to get its location path, e.g. "Garage > Shelf 3 > Blue Bin".',
            '3. If several items match, list each with its location so I can pick.',
            '4. If you still cannot find it, call search-items with trashed true in case it was deleted, and tell me if it was.',
            '',
            'Answer with the location first, in one line.',
        ]));
    }

    /** @return array<int, Argument> */
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'item',
                description: 'What you are looking for, e.g. "camping lanterns".',
                required: true,
            ),
        ];
    }

    /** @param  array<string, mixed>  $context */
    public function complete(string $argument, string $value, array $context): CompletionResponse
    {
        $team = Auth::user()?->currentTeam;

        if ($argument !== 'item' || $team === null) {
            return CompletionResponse::empty();
        }

        return CompletionResponse::result(
            $team->items()
                ->when(trim($value) !== '', fn ($query) => $query->whereLike('name', '%' . trim($value) . '%'))
                ->orderBy('name')
                ->limit(100)
                ->pluck('name')
                ->unique()
                ->values()
                ->all(),
        );
    }
}
