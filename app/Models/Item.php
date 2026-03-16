<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ItemType;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'parent_id',
        'type',
        'name',
        'photo_path',
        'metadata',
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

    /** @return BaseCollection<int, int> */
    public function descendantIds(): BaseCollection
    {
        return collect(DB::select(
            'with recursive descendants as (
                select id from items where parent_id = ?
                union all
                select items.id from items inner join descendants on descendants.id = items.parent_id
            ) select id from descendants',
            [$this->id],
        ))->pluck('id');
    }

    public function purge(): void
    {
        $this->children()->update(['parent_id' => null]);
        $this->delete();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'metadata' => 'array',
        ];
    }

    protected function truncatedName(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::limit($this->name, 75),
        );
    }
}
