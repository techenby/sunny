<?php

namespace App\Concerns;

use App\Models\Team;
use Native\Mobile\Attributes\Computed;

trait ChoosesOwner
{
    public ?int $userId = null;

    /**
     * @return list<array{id: int, name: string, initials: string}>
     */
    #[Computed]
    public function ownerOptions(): array
    {
        $team = $this->ownerTeam();

        return $team?->hasSeveralMembers() ? $team->members : [];
    }

    public function chooseOwner(int $id): void
    {
        $this->userId = collect($this->ownerOptions)->contains('id', $id) ? $id : null;
    }

    protected function fillOwner(?int $userId): void
    {
        $this->userId = collect($this->ownerOptions)->contains('id', $userId) ? $userId : null;
    }

    protected function ownerPayload(bool $creating): array
    {
        if ($this->ownerOptions !== []) {
            return ['user_id' => $this->userId];
        }

        $soleMember = $creating ? $this->ownerTeam()?->soleMember() : null;

        return $soleMember === null ? [] : ['user_id' => $soleMember['id']];
    }

    private function ownerTeam(): ?Team
    {
        return $this->teamId === null ? null : Team::query()->find($this->teamId);
    }
}
