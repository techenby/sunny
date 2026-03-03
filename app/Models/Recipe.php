<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RecipeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use HasFactory;

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
            $recipe->slug = Str::slug($recipe->name);
        });

        static::updating(function ($recipe) {
            if ($recipe->isDirty('name')) {
                $recipe->slug = Str::slug($recipe->name);
            }
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

    /** @return HasMany<self> */
    public function remixes(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
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
