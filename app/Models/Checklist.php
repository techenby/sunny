<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChecklistType;
use Database\Factories\ChecklistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['team_id', 'user_id', 'type', 'name'])]
class Checklist extends Model
{
    /** @use HasFactory<ChecklistFactory> */
    use HasFactory;
    use SoftDeletes;

    /** @return HasMany<ChecklistItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**  @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        return $this->items()->exists()
            && ! $this->items()->whereNull('completed_at')->exists();
    }

    public function progress(): int
    {
        $total = $this->items()->count();

        if ($total === 0) {
            return 0;
        }

        return (int) round($this->items()->whereNotNull('completed_at')->count() / $total * 100);
    }

    public function reset(): void
    {
        $this->items()->update([
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    /** @param  Builder<Checklist>  $query */
    #[Scope]
    protected function household(Builder $query): void
    {
        $query->whereNull('user_id');
    }

    /** @param Builder<Checklist> $query */
    #[Scope]
    protected function ofType(Builder $query, ChecklistType $type): void
    {
        $query->where('type', $type);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ChecklistType::class,
        ];
    }
}
