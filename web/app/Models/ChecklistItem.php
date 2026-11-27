<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LimitedPerOwner;
use Database\Factories\ChecklistItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['checklist_id', 'name', 'url', 'position', 'client_uuid'])]
class ChecklistItem extends Model
{
    /** @use HasFactory<ChecklistItemFactory> */
    use HasFactory;
    use LimitedPerOwner;

    public const int MAX_PER_OWNER = 500;

    protected static function booted(): void
    {
        static::creating(function (ChecklistItem $item): void {
            $item->position ??= (int) static::query()
                ->where('checklist_id', $item->checklist_id)
                ->max('position') + 1;
        });
    }

    /** @return BelongsTo<Checklist, $this> */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function complete(?User $user = null): void
    {
        $this->update([
            'completed_at' => now(),
            'completed_by' => $user?->id,
        ]);
    }

    public function uncomplete(): void
    {
        $this->update([
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    public function setCompleted(bool $completed, ?User $user = null): void
    {
        if ($this->isCompleted() === $completed) {
            return;
        }

        $completed ? $this->complete($user) : $this->uncomplete();
    }

    /** @param Builder<static> $query */
    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    /** @param Builder<static> $query */
    #[Scope]
    protected function incomplete(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }

    protected function ownerColumn(): string
    {
        return 'checklist_id';
    }

    protected function ownerLimitMessage(): string
    {
        return __('A list can have up to :count items.', ['count' => self::MAX_PER_OWNER]);
    }
}
