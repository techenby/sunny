<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Lists\Concerns;

use App\Enums\ChecklistType;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\ObjectType;
use Illuminate\JsonSchema\Types\Type;

trait FormatsChecklists
{
    /** @return array<string, mixed> */
    protected function checklistCounts(): array
    {
        return [
            'items',
            'items as completed_items_count' => fn (Builder $query) => $query->whereNotNull('completed_at'),
        ];
    }

    protected function loadChecklistDetails(Checklist $checklist): Checklist
    {
        return $checklist
            ->load(['user', 'items.completedBy'])
            ->loadCount($this->checklistCounts());
    }

    /** @return array<string, mixed> */
    protected function formatChecklistSummary(Checklist $checklist): array
    {
        $itemCount = (int) $checklist->items_count;
        $completedCount = (int) $checklist->completed_items_count;

        return [
            'id' => $checklist->id,
            'name' => $checklist->name,
            'type' => $checklist->type->value,
            'user_id' => $checklist->user_id,
            'owner_name' => $checklist->user?->name,
            'item_count' => $itemCount,
            'completed_count' => $completedCount,
            'progress' => $itemCount === 0 ? 0 : (int) round($completedCount / $itemCount * 100),
        ];
    }

    /** @return array<string, mixed> */
    protected function formatChecklist(Checklist $checklist): array
    {
        return [
            ...$this->formatChecklistSummary($checklist),
            'items' => $checklist->items
                ->map(fn (ChecklistItem $item): array => $this->formatChecklistItem($item))
                ->all(),
            'created_at' => $checklist->created_at->toIso8601String(),
            'updated_at' => $checklist->updated_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    protected function formatChecklistItem(ChecklistItem $item): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'position' => $item->position,
            'completed' => $item->isCompleted(),
            'completed_at' => $item->completed_at?->toIso8601String(),
            'completed_by_name' => $item->completedBy?->name,
        ];
    }

    /** @return array<string, Type> */
    protected function checklistSummaryOutputSchema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'type' => $schema->string()->enum(ChecklistType::class)->required(),
            'user_id' => $schema->integer()->nullable()->required()
                ->description('The id of the team member who owns the list, or null for a household list.'),
            'owner_name' => $schema->string()->nullable()->required()
                ->description('The name of the owner, or null for a household list.'),
            'item_count' => $schema->integer()->required(),
            'completed_count' => $schema->integer()->required(),
            'progress' => $schema->integer()->min(0)->max(100)->required()
                ->description('Percentage of items completed, from 0 to 100.'),
        ];
    }

    /** @return array<string, Type> */
    protected function checklistOutputSchema(JsonSchema $schema): array
    {
        return [
            ...$this->checklistSummaryOutputSchema($schema),
            'items' => $schema->array()->items($this->checklistItemOutputSchema($schema))->required()
                ->description('The items on the list, in position order.'),
            'created_at' => $schema->string()->required(),
            'updated_at' => $schema->string()->required(),
        ];
    }

    protected function checklistItemOutputSchema(JsonSchema $schema): ObjectType
    {
        return $schema->object($this->checklistItemOutputProperties($schema));
    }

    /** @return array<string, Type> */
    protected function checklistItemOutputProperties(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'position' => $schema->integer()->required(),
            'completed' => $schema->boolean()->required(),
            'completed_at' => $schema->string()->nullable()->required(),
            'completed_by_name' => $schema->string()->nullable()->required()
                ->description('The name of the team member who completed the item, if known.'),
        ];
    }
}
