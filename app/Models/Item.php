<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemType;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    /** @var string */
    protected $table = 'inventory_items';

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'parent_id',
        'type',
        'name',
    ];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<self, $this> */
    public function items(): HasMany
    {
        return $this->children()->where('type', ItemType::Item);
    }

    /** @param Builder<self> $query */
    #[Scope]
    protected function withAllItemsCount(Builder $query): void
    {
        $query->selectRaw('inventory_items.*, (
            WITH RECURSIVE descendant_items AS (
                SELECT id FROM inventory_items AS c WHERE c.id = inventory_items.id
                UNION ALL
                SELECT ch.id FROM inventory_items AS ch
                INNER JOIN descendant_items AS di ON ch.parent_id = di.id
            )
            SELECT COUNT(*) FROM inventory_items AS leaf
            WHERE leaf.parent_id IN (SELECT id FROM descendant_items)
            AND leaf.type = \'item\'
        ) as all_items_count');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
        ];
    }
}
