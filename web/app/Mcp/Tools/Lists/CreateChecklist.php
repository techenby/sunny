<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Lists;

use App\Enums\ChecklistType;
use App\Mcp\Tools\Lists\Concerns\FormatsChecklists;
use App\Models\Checklist;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Create a checklist on the current team: a to-do list, shopping list, or wish list. Optionally add items in the same call, e.g. a shopping list with "milk" and "eggs". Leave user_id empty for a household list shared by everyone, or pass a team member\'s id to make it theirs.')]
class CreateChecklist extends Tool
{
    use FormatsChecklists;

    public function handle(Request $request): ResponseFactory
    {
        Gate::forUser($request->user())->authorize('create', Checklist::class);

        $team = $request->user()->currentTeam;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ChecklistType::class)],
            'user_id' => ['nullable', 'integer', Rule::in($team->members()->pluck('users.id'))],
            'items' => ['nullable', 'array'],
            'items.*' => ['required', 'string', 'max:255'],
        ], [
            'type.enum' => 'The type must be one of: todo, shopping, wishlist.',
            'user_id.in' => 'The user_id must be the id of a member of the current team, or null for a household list.',
            'items.*.required' => 'Each item must be a non-empty name.',
            'items.*.string' => 'Each item must be a name given as a string.',
            'items.*.max' => 'Each item name may not be longer than 255 characters.',
        ]);

        $checklist = DB::transaction(function () use ($team, $validated): Checklist {
            $checklist = $team->checklists()->create(Arr::except($validated, 'items'));

            foreach ($validated['items'] ?? [] as $name) {
                $checklist->items()->create(['name' => $name]);
            }

            return $checklist;
        });

        return Response::structured($this->formatChecklist($this->loadChecklistDetails($checklist)));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->max(255)
                ->description('The name of the list, e.g. "Groceries" or "Weekend chores".')
                ->required(),
            'type' => $schema->string()
                ->enum(ChecklistType::class)
                ->description('The kind of list: "todo", "shopping", or "wishlist".')
                ->required(),
            'user_id' => $schema->integer()
                ->nullable()
                ->description('The id of the team member who owns the list. Omit or pass null for a household list.'),
            'items' => $schema->array()
                ->items($schema->string()->max(255))
                ->description('Optional item names to add to the new list, in order.'),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->checklistOutputSchema($schema);
    }
}
