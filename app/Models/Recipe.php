<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\HasSlug;
use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;
    use HasSlug;

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'parent_id',
        'name',
        'slug',
        'share_token',
        'source',
        'servings',
        'prep_time',
        'cook_time',
        'total_time',
        'description',
        'ingredients',
        'instructions',
        'notes',
        'nutrition',
        'tags',
        'photo_path',
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

    /** @return HasMany<Recipe, $this> */
    public function remixes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function shortenedSource(): ?string
    {
        if (! $this->source) {
            return null;
        }

        if ($this->isSourceUrl()) {
            $host = parse_url($this->source, PHP_URL_HOST);

            return str_replace('www.', '', $host ?? $this->source);
        }

        return Str::limit($this->source, 30);
    }

    public function isSourceUrl(): bool
    {
        return filter_var($this->source, FILTER_VALIDATE_URL) !== false;
    }

    public function enableSharing(): void
    {
        $this->update(['share_token' => Str::uuid()->toString()]);
    }

    public function disableSharing(): void
    {
        $this->update(['share_token' => null]);
    }

    public function isShared(): bool
    {
        return ! is_null($this->share_token);
    }

    /** @param  Builder<static>  $query */
    protected function scopeSlugUniqueness(Builder $query): void
    {
        $query->where('team_id', $this->team_id);
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
        ];
    }
}
