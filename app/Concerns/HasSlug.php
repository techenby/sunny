<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->slug)) {
                $model->slug = $model->generateUniqueSlug($model->name);
            }
        });

        static::updating(function (self $model): void {
            if ($model->isDirty('name')) {
                $model->slug = $model->generateUniqueSlug($model->name);
            }
        });
    }

    public function generateUniqueSlug(string $name): string
    {
        $baseSlug = Str::slug($name);

        $existingSlugs = static::query()
            ->where('slug', 'like', $baseSlug . '%')
            ->when($this->exists, fn (Builder $query) => $query->where('id', '!=', $this->id))
            ->tap(fn (Builder $query) => $this->scopeSlugUniqueness($query))
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

    /** @param  Builder<static>  $query */
    protected function scopeSlugUniqueness(Builder $query): void
    {
        // Override in models that need scoped uniqueness (e.g. per-team).
    }
}
