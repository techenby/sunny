<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Lists;

use App\Enums\ChecklistType;
use App\Mcp\Tools\Lists\Concerns\FormatsChecklists;
use App\Models\Checklist;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description("List the current team's checklists (to-do, shopping, and wish lists), ordered by name, with each list's owner and completion progress. Use the get-checklist tool to see a list's items.")]
class ListChecklists extends Tool
{
    use FormatsChecklists;

    public function handle(Request $request): ResponseFactory
    {
        Gate::forUser($request->user())->authorize('viewAny', Checklist::class);

        $validated = $request->validate([
            'type' => ['nullable', Rule::enum(ChecklistType::class)],
        ], [
            'type.enum' => 'The type must be one of: todo, shopping, wishlist.',
        ]);

        $checklists = $request->user()->currentTeam->checklists()
            ->when(isset($validated['type']), fn ($query) => $query->ofType(ChecklistType::from($validated['type'])))
            ->with('user')
            ->withCount($this->checklistCounts())
            ->orderBy('name')
            ->get();

        return Response::structured([
            'count' => $checklists->count(),
            'checklists' => $checklists
                ->map(fn (Checklist $checklist): array => $this->formatChecklistSummary($checklist))
                ->all(),
        ]);
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()
                ->enum(ChecklistType::class)
                ->description('Only return lists of this type: "todo", "shopping", or "wishlist".')
                ->nullable(),
        ];
    }

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'count' => $schema->integer()->required(),
            'checklists' => $schema->array()
                ->items($schema->object($this->checklistSummaryOutputSchema($schema)))
                ->required(),
        ];
    }
}
