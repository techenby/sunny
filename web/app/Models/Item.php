<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LimitedPerOwner;
use App\Enums\ItemType;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @property array<array-key, mixed>|null $metadata
 * @property-read array<string, string> $details
 */
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;
    use LimitedPerOwner;

    public const int MAX_PER_OWNER = 5_000;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (Item $item): void {
            if ($item->parent_id === null || ! $item->isDirty('parent_id')) {
                return;
            }

            $parentId = (int) $item->parent_id;

            throw_unless(
                self::query()->whereKey($parentId)->where('team_id', $item->team_id)->exists(),
                ValidationException::withMessages(['parent_id' => [__('That place isn’t in this team.')]]),
            );

            throw_if(
                $item->exists && ($parentId === $item->id || $item->descendantIds()->contains($parentId)),
                ValidationException::withMessages(['parent_id' => [__('An item can’t go inside itself or inside something it holds.')]]),
            );
        });
    }

    /** @param  array<string, mixed>  $options */
    public function save(array $options = []): bool
    {
        if (! $this->exists || $this->parent_id === null || ! $this->isDirty('parent_id')) {
            return parent::save($options);
        }

        return DB::transaction(function () use ($options): bool {
            Team::query()->whereKey($this->team_id)->lockForUpdate()->first();

            return parent::save($options);
        });
    }

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

    /** @return HasMany<ItemPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(ItemPhoto::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasOne<ItemPhoto, $this> */
    public function primaryPhoto(): HasOne
    {
        return $this->hasOne(ItemPhoto::class)->ofMany(['position' => 'min', 'id' => 'min']);
    }

    /** @param Builder<static> $query */
    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $anywhere = '%' . Str::lower($term) . '%';

        $detailMatches = $this->getConnection()->getDriverName() === 'pgsql'
            ? "case when json_typeof(items.metadata) = 'object' then exists (select 1 from json_each_text(items.metadata) as detail where lower(detail.value) like ?) else false end"
            : 'exists (select 1 from json_each(items.metadata) as detail where lower(detail.value) like ?)';

        $query->where(fn (Builder $query) => $query
            ->whereRaw('lower(name) like ?', [$anywhere])
            ->orWhereRaw($detailMatches, [$anywhere]));
    }

    /** @return BaseCollection<int, int> */
    public function descendantIds(): BaseCollection
    {
        return collect(DB::select(
            'with recursive descendants as (
                select id from items where parent_id = ?
                union
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

    public function deletionWarning(): string
    {
        $warning = __('“:name” will be deleted for everyone on your team.', ['name' => $this->name]);
        $count = $this->children_count ?? $this->children()->count();

        if ($count === 0) {
            return $warning;
        }

        return $warning . ' ' . trans_choice(
            'The :count thing inside it will move to “Not in a place yet”.|The :count things inside it will move to “Not in a place yet”.',
            $count,
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ItemType::class,
            'metadata' => 'array',
        ];
    }

    /** @return Attribute<array<string, string>, never> */
    protected function details(): Attribute
    {
        return Attribute::get(fn (): array => collect($this->metadata ?? [])
            ->mapWithKeys(fn (mixed $value, int|string $label): array => [
                (string) $label => is_string($value) ? $value : (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ])
            ->all());
    }

    protected function truncatedName(): Attribute
    {
        return Attribute::make(
            get: fn () => Str::limit($this->name, 75),
        );
    }

    protected function photoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->primary()?->url,
        );
    }

    protected function thumbUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->primary()->thumb_url ?? $this->photo_url,
        );
    }

    private function primary(): ?ItemPhoto
    {
        return $this->relationLoaded('photos') ? $this->photos->first() : $this->primaryPhoto;
    }

    protected function ownerColumn(): string
    {
        return 'team_id';
    }

    protected function ownerLimitMessage(): string
    {
        return __('A team can have up to :count items, counting recently deleted ones.', ['count' => number_format(self::MAX_PER_OWNER)]);
    }
}
