<?php

namespace App\Concerns;

use App\Enums\ChecklistType;
use Native\Mobile\Attributes\Computed;

trait ManagesListForm
{
    use SavesSunnyRecord;

    public string $name = '';

    public int $typeIndex = 0;

    public string $error = '';

    /**
     * @param  array{team_id: int, name: string, type: ChecklistType}  $checklist
     */
    public function fillFromList(array $checklist): void
    {
        $this->initializeTeam($checklist['team_id']);
        $this->name = $checklist['name'];
        $this->typeIndex = (int) array_search($checklist['type'], ChecklistType::cases(), strict: true);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function typeOptions(): array
    {
        return array_map(fn (ChecklistType $type): string => $type->label(), ChecklistType::cases());
    }

    protected function listPayload(): array
    {
        return [
            'name' => trim($this->name),
            'type' => (ChecklistType::cases()[$this->typeIndex] ?? ChecklistType::Todo)->value,
        ];
    }

    protected function validationError(): string
    {
        if (trim($this->name) === '') {
            return 'Give the list a name.';
        }

        if (mb_strlen(trim($this->name)) > 255) {
            return 'The name is too long (255 characters max).';
        }

        return '';
    }
}
