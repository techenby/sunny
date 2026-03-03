<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

class Recipe extends Model implements HasMedia
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;
    use HasTags;
    use InteractsWithMedia;

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'parent_id',
        'name',
        'slug',
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
    ];

    protected static function booted(): void
    {
        static::creating(function ($recipe) {
            $recipe->slug = $recipe->generateUniqueSlug($recipe->name);
        });

        static::updating(function ($recipe) {
            if ($recipe->isDirty('name')) {
                $recipe->slug = $recipe->generateUniqueSlug($recipe->name);
            }
        });
    }

    public function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);

        $existingSlugs = self::query()
            ->where('team_id', $this->team_id)
            ->where('slug', 'like', $baseSlug . '%')
            ->when($this->exists, fn ($query) => $query->where('id', '!=', $this->id))
            ->pluck('slug');

        if ($existingSlugs->doesntContain($baseSlug)) {
            return $baseSlug;
        }

        $counter = 1;

        while ($existingSlugs->contains($baseSlug . '-' . $counter)) {
            $counter++;
        }

        return $baseSlug . '-' . $counter;
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

    /** @return HasMany<Recipe, $this> */
    public function remixes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function createRemix(): self
    {
        $remix = $this->replicate();
        $remix->name = $this->name . ' (Remix)';
        $remix->parent_id = $this->id;
        $remix->save();

        return $remix;
    }

    public function shortenedSource(): ?string
    {
        if (! $this->source) {
            return null;
        }

        if (filter_var($this->source, FILTER_VALIDATE_URL)) {
            $host = parse_url($this->source, PHP_URL_HOST);

            return str_replace('www.', '', $host ?? $this->source);
        }

        return Str::limit($this->source, 30);
    }
}
