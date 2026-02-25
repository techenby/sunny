<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContainerType;
use Database\Factories\ContainerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Container extends Model
{
    /** @use HasFactory<ContainerFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'parent_id',
        'type',
        'name',
        'category',
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

    /** @return HasMany<Item, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ContainerType::class,
        ];
    }
}
