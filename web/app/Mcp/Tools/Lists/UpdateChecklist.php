<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Lists;

use App\Enums\ChecklistType;
use App\Mcp\Tools\Lists\Concerns\FormatsChecklists;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description("Rename a checklist, change its type, or change its owner. Only the provided fields are changed. Pass user_id as null to make it a household list. To change the list's items, use the add-checklist-items, update-checklist-item, and remove-checklist-item tools.")]
class UpdateChecklist extends Tool
{
    use FormatsChecklists;

    public function handle(Request $request): Response|ResponseFactory
    {
        $team = $request->user()->currentTeam;

        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::enum(ChecklistType::class)],
            'user_id' => ['sometimes', 'nullable', 'integer', Rule::in($team->members()->pluck('users.id'))],
        ], [
            'type.enum' => 'The type must be one of: todo, shopping, wishlist.',
            'user_id.in' => 'The user_id must be the id of a member of the current team, or null for a household list.',
        ]);

        $checklist = $team->checklists()->find($validated['id']);

        if ($checklist === null) {
            return Response::error('Checklist not found.');
        }

        Gate::forUser($request->user())->authorize('update', $checklist);

        $data = Arr::except($validated, 'id');

        if ($data === []) {
            return Response::error('Provide at least one of name, type, or user_id to update.');
        }

        $checklist->update($data);

        return Response::structured($this->formatChecklist($this->loadChecklistDetails($checklist)));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The id of the checklist to update. Use the list-checklists tool to find ids.')
                ->required(),
            'name' => $schema->string()
                ->max(255)
                ->description('A new name for the list.'),
            'type' => $schema->string()
                ->enum(ChecklistType::class)
                ->description('A new type for the list: "todo", "shopping", or "wishlist".'),
            'user_id' => $schema->integer()
                ->nullable()
                ->description('The id of the team member who should own the list, or null to make it a household list.'),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->checklistOutputSchema($schema);
    }
}
